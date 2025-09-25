<?php

namespace App\Controller\Home\Config;

use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\MemberAddressModel;
use App\Request\LibValidation;
use App\Service\AddressService;
use App\Service\ConfigService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "config/address")]
class AddressController extends HomeBaseController
{
    /**
     * @DOC 地址管理列表
     */
    #[RequestMapping(path: 'index', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $data   = \Hyperf\Support\make(AddressService::class)->getAddress($param, $member);
        return $this->response->json($data);
    }

    /**
     * @DOC 地址详情
     */
    #[RequestMapping(path: 'detail', methods: 'post')]
    public function detail(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $data   = \Hyperf\Support\make(AddressService::class)->getAddressDetail($param, $member);
        return $this->response->json($data);
    }

    /**
     * @DOC 添加地址
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = (new AddressService())->addAddress($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 地址修改
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param    = $request->all();
        $userInfo = $this->request->UserInfo;
        $result   = (new AddressService())->editAddress($param, $userInfo);
        return $this->response->json($result);
    }

    /**
     * @DOC 调整状态
     */
    #[RequestMapping(path: 'status', methods: 'post')]
    public function status(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'address_id' => ['required', 'integer'],
                'status'     => ['required', Rule::in([0, 1])],
            ], [
                'address_id.required' => '地址必选',
                'address_id.integer'  => '地址错误',
                'status.required'     => '状态必选',
                'status.in'           => '状态错误',
            ]);
        $where['address_id'] = $param['address_id'];
        $where['member_uid'] = $member['uid'];
        $MemberAddressDb     = MemberAddressModel::where($where)->first();
        if (empty($MemberAddressDb)) {
            throw new HomeException('错误：当前数据不存在');
        }
        if ($MemberAddressDb->where($where)->update(['status' => $param['status']])) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);
    }

    /**
     * @DOC 调整状态
     */
    #[RequestMapping(path: 'del', methods: 'post')]
    public function del(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'address_id' => ['required', 'integer']
            ], [
                'address_id.required' => '地址必选',
                'address_id.integer'  => '地址错误'
            ]);
        $where['address_id'] = $param['address_id'];
        $where['member_uid'] = $member['uid'];
        $MemberAddressDb     = MemberAddressModel::where($where)->first();
        if (empty($MemberAddressDb)) {
            throw new HomeException('错误：当前数据不存在');
        }
        if (MemberAddressModel::where($where)->delete()) {
            return $this->response->json(['code' => 200, 'msg' => '删除成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '删除失败', 'data' => []]);
    }


    /**
     * @DOC 设置默认地址
     */
    #[RequestMapping(path: 'default', methods: 'post')]
    public function default(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = (new AddressService())->default($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 行政区域
     */
    #[RequestMapping(path: 'area', methods: 'get,post')]
    public function area(RequestInterface $request): ResponseInterface
    {
        $param            = $request->all();
        $parent_agent_uid = $request->UserInfo['parent_agent_uid'];
        $result           = (new ConfigService())->area($param, $parent_agent_uid);
        return $this->response->json($result);
    }

}
