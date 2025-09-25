<?php

namespace App\Controller\Home\Config;

use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\CountryCodeModel;
use App\Model\FreightCompanyModel;
use App\Model\MemberLineModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;
use function App\Common\batchUpdateSql;

#[Controller(prefix: "config/freight")]
class FreightController extends AbstractController
{
    /**
     * @DOC 货运公司列表
     */
    #[RequestMapping(path: 'index', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $where[] = ['member_uid', '=', $request->UserInfo['parent_agent_uid']];
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['company_name|company_code', 'like', '%' . $param['keyword'] . '%'];
        }
        if (Arr::hasArr($param, 'country_id')) {
            $where[] = ['country_id', '=', $param['country_id']];
        }
        if (Arr::hasArr($param, 'status')) {
            $where[] = ['status', '=', $param['status']];
        }

        $data = FreightCompanyModel::where($where)
            ->with(['item', 'country'])
            ->orderBy('add_time', 'desc')
            ->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items(),
            ]]);
    }

    /**
     * @DOC 公司新增
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        # 验证参数
        $this->checkValidate($param, $member);
        # 获取参数
        list($company, $item) = $this->getData($param, $member);

        Db::beginTransaction();
        try {
            $company_id        = Db::table('freight_company')->insertGetId($company);
            $Add['company_id'] = $company_id;
            $item              = Arr::pushArr($Add, $item);
            Db::table('freight_company_item')->insert($item);
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '添加成功', 'data' => []]);
        } catch (\Exception $e) {
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * @DOC 货运公司编辑
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        # 验证参数
        $this->checkValidate($param, $member);
        $where[] = ['company_id', '=', $param['company_id']];
        $where[] = ['member_uid', '=', $member['uid']];
        $data    = FreightCompanyModel::where($where)->with('item')->first();
        if (empty($data)) {
            throw new HomeException("编辑失败：当前公司信息不存在");
        }
        $data      = $data->toArray();
        $itemIdArr = Arr::hasArr($data, 'item') ? array_column($data['item'], 'item_id') : [];

        # 获取参数
        list($company, $item) = $this->getData($param, $member, 'edit');

        if (Arr::hasArr($param, 'item')) {
            $mLinedArr = array_column($param['item'], 'member_line_id');
            $mLinArrDb = MemberLineModel::whereIn('member_line_id', $mLinedArr)->get()->toArray();
            $mLinArrDb = array_column($mLinArrDb, null, 'member_line_id');
        }
        $item = [];
        foreach ($param['item'] as $key => $val) {
            if (Arr::hasArr($val, 'item_id') && in_array($val['item_id'], $itemIdArr)) {
                $item[$key]['item_id']        = $val['item_id'];
                $item[$key]['company_id']     = $data['company_id'];
                $mLineId                      = $val['member_line_id'];
                $item[$key]['line_id']        = $mLinArrDb[$mLineId]['line_id'];
                $item[$key]['uid']            = $member['uid'];
                $item[$key]['member_line_id'] = $mLineId;
                $item[$key]['company_cfg_id'] = $val['company_cfg_id'];
                $item[$key]['code']           = $val['code'];
                $item[$key]['status']         = $val['status'];

                $key = array_search($val['item_id'], $itemIdArr);
                unset($itemIdArr[$key]);
            } else {
                $itemInsert[$key]['company_id']     = $data['company_id'];
                $mLineId                            = $val['member_line_id'];
                $itemInsert[$key]['line_id']        = $mLinArrDb[$mLineId]['line_id'];
                $itemInsert[$key]['uid']            = $member['uid'];
                $itemInsert[$key]['member_line_id'] = $mLineId;
                $itemInsert[$key]['company_cfg_id'] = $val['company_cfg_id'];
                $itemInsert[$key]['code']           = $val['code'];
                $itemInsert[$key]['status']         = $val['status'];
            }
        }
        Db::beginTransaction();
        try {
            Db::table('freight_company')
                ->where('company_id', $param['company_id'])
                ->update($company);
            $updateBrandDataSql = batchUpdateSql('freight_company_item', $item);
            Db::update($updateBrandDataSql);
            if (!empty($itemInsert)) {
                Db::table('freight_company_item')->insert($itemInsert);
            }
            if (!empty($itemIdArr)) {
                Db::table('freight_company_item')->whereIn('item_id', $itemIdArr)->delete();
            }
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        } catch (\Exception $e) {
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * @DOC 修改状态
     */
    #[RequestMapping(path: 'status', methods: 'post')]
    public function status(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'company_id' => ['required', 'integer'],
            'status'     => ['required', Rule::in([0, 1])],
        ], [
            'company_id.required' => '货运公司错误',
            'company_id.integer'  => '货运公司错误',
            'status.required'     => '状态必选',
            'status.in'           => '状态错误',
        ]);
        $where['company_id'] = $param['company_id'];
        $where['member_uid'] = $request->UserInfo['uid'];
        $company             = FreightCompanyModel::where($where)->first();
        if (empty($company)) {
            throw new HomeException('处理失败：当前数据不存在');
        }
        if (FreightCompanyModel::where($where)->update(['status' => $param['status'] ?? 0])) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);
    }

    /**
     * @DOC 校验参数
     */
    protected function checkValidate($param, $member, $type = 'add')
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $rules         = [
            'country_id'      => ['required', 'integer'],
            'company_name'    => ['required'],
            'company_code'    => ['required', 'min:3', 'max:8'],
            'contacts'        => ['required', 'min:2'],
            'phone_before'    => ['required', 'min:2', 'integer'],
            'contact_phone'   => ['required', 'min:8'],
            'contact_address' => ['required', 'min:2'],
            'item'            => ['required', 'array'],
        ];
        $message       = [
            'country_id.required'      => '国家必选',
            'country_id.integer'       => '国家错误',
            'company_name.required'    => '公司名称必填',
            'company_code.required'    => '公司代码必填',
            'company_code.min'         => '公司代码最少长度3位',
            'company_code.max'         => '公司代码最大长度8位',
            'contacts.min'             => '联系人最少长度2位',
            'contacts.required'        => '联系人必填',
            'phone_before.required'    => '国际前缀必填',
            'phone_before.min'         => '国际前缀最少两位',
            'phone_before.integer'     => '国际前缀错误',
            'contact_phone.required'   => '手机号码必填',
            'contact_phone.min'        => '手机号码最少8位',
            'contact_address.required' => '联系人地址必填',
            'contact_address.min'      => '联系人地址最少两位',
            'item.required'            => '明细必填',
            'item.array'               => '明细错误',
        ];
        if ($type == 'edit') {
            $rules['company_id']            = ['required', 'integer'];
            $message['company_id.required'] = '货运公司必选';
            $message['company_id.integer']  = '货运公司错误';
        }
        $LibValidation->validate($param, $rules, $message);
    }

    /**
     * @DOC 获取参数
     */
    public function getData($param, $member, $type = 'add'): array
    {
        $company['company_name']    = $param['company_name'];
        $company['member_uid']      = $member['uid'];
        $company['company_code']    = $param['company_code'];
        $company['country_id']      = $param['country_id'];
        $company['status']          = $param['status'] ?? 0;
        $company['contacts']        = $param['contacts'];
        $company['phone_before']    = $param['phone_before'];
        $company['contact_phone']   = $param['contact_phone'];
        $company['contact_address'] = $param['contact_address'];

        $item = [];
        if ($type == 'add') {
            $company['add_time'] = time();
            if (Arr::hasArr($param, 'item')) {
                $mLinedArr = array_column($param['item'], 'member_line_id');
                $mLinArrDb = MemberLineModel::whereIn('member_line_id', $mLinedArr)->get()->toArray();
                $mLineArr  = array_column($mLinArrDb, null, 'member_line_id');
            }

            foreach ($param['item'] as $key => $val) {
                try {
                    $line_id = $mLineArr[$val['member_line_id']]['line_id'];
                } catch (\Exception $e) {
                    throw new HomeException('未查询到当前线路');
                }
                $item[$key]['line_id']        = $line_id;
                $item[$key]['uid']            = $member['uid'];
                $item[$key]['member_line_id'] = $val['member_line_id'];
                $item[$key]['company_cfg_id'] = $val['company_cfg_id'];
                $item[$key]['code']           = $val['code'];
                $item[$key]['status']         = $val['status'] ?? 0;
            }
        }
        return [$company, $item];
    }


}
