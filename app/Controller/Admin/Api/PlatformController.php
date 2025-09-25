<?php

declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\ApiPlatformModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;
use function App\Common\batchUpdateSql;

#[Controller(prefix: '/', server: 'httpAdmin')]
class PlatformController extends AdminBaseController
{

    /**
     * @DOC 接口平台列表
     */
    #[RequestMapping(path: 'api/platform/lists', methods: 'post')]
    public function lists(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $data  = ApiPlatformModel::query();
        if (Arr::hasArr($param, 'keyword')) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('platform_name', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('platform_code', 'like', '%' . $param['keyword'] . '%');
            });
        }

        $data = $data->with(
            [
                'item', 'cfg'
            ]
        )->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items(),
            ]
        ]);
    }

    /**
     * @DOC  添加
     */
    #[RequestMapping(path: 'api/platform/add', methods: 'post')]
    public function handleAdd(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'country_id'      => ['required'],
                'platform_name'   => ['required'],
                'platform_code'   => ['required'],
                'platform_cfg_id' => ['required'],
                'api_url'         => ['required'],
                'api_url_test'    => ['required'],
                'platform_url'    => ['required'],
                'info'            => ['nullable'],
                'status'          => ['required', Rule::in([0, 1])],
            ], [
                'country_id.required'      => '国家地区必填',
                'platform_name.required'   => '平台名称必填',
                'platform_code.required'   => '平台代码必填',
                'api_url.required'         => '接口编码必填',
                'api_url_test.required'    => '平台正式接口地址必填',
                'platform_url.required'    => '平台接口测试地址必填',
                'platform_cfg_id.required' => '平台类型必填',
                'status.required'          => '状态错误',
                'status.in'                => '状态错误',
            ]);

        $where['platform_code'] = strtolower($param['platform_code']);

        $data = ApiPlatformModel::where($where)->first();
        if (!empty($data)) {
            throw new HomeException('添加失败：该平台已经存在');
        }
        $param['platform_code'] = strtolower($param['platform_code']);
        $param['add_time']      = time();
        if (ApiPlatformModel::insert($param)) {
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 调整状态
     */
    #[RequestMapping(path: 'api/platform/status', methods: 'post')]
    public function handleStatus(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'platform_id'   => ['required'],
                'status'        => ['required'],
                'platform_code' => ['required'],
            ], [
                'platform_id.required'   => '接口不存在',
                'status.required'        => '状态错误',
                'platform_code.required' => '接口编码错误',
            ]);
        $where[] = ['platform_id', '=', $param['platform_id']];
        $data    = ApiPlatformModel::where($where)->first();
        if (empty($data)) {
            throw new HomeException('编辑失败：该接口数据不存在');
        }
        $data = $data->toArray();
        if ($data['status'] == $param['status']) {
            throw new HomeException('当前状态无需更新');
        }
        if ($param['platform_code'] != $data['platform_code']) {
            throw new HomeException('修改失败：当前接口代码不一致');
        }

        if (ApiPlatformModel::where($where)->update(['status' => $param['status']])) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 更新
     */
    #[RequestMapping(path: 'api/platform/edit', methods: 'post')]
    public function handleEdit(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'platform_id'     => ['required'],
                'country_id'      => ['required'],
                'platform_name'   => ['required'],
                'platform_code'   => ['required'],
                'platform_cfg_id' => ['required'],
                'api_url'         => ['required'],
                'api_url_test'    => ['required'],
                'platform_url'    => ['required'],
                'info'            => ['nullable'],
                'status'          => ['required', Rule::in([0, 1])],
            ], [
                'platform_id.required'     => '平台不存在必填',
                'country_id.required'      => '国家地区必填',
                'platform_name.required'   => '平台名称必填',
                'platform_code.required'   => '平台代码必填',
                'api_url.required'         => '接口编码必填',
                'api_url_test.required'    => '平台正式接口地址必填',
                'platform_url.required'    => '平台接口测试地址必填',
                'platform_cfg_id.required' => '平台类型必填',
                'status.required'          => '状态错误',
                'status.in'                => '状态错误',
            ]);
        $where['platform_code'] = strtolower($param['platform_code']);
        $data                   = ApiPlatformModel::where($where)->first();
        if (!empty($data) && $data['platform_id'] != $param['platform_id']) {
            throw new HomeException('编辑失败：该平台Code被其他平台使用');
        }
        if (ApiPlatformModel::where('platform_id', $param['platform_id'])->update($param)) {
            $result['code'] = 200;
            $result['msg']  = '编辑成功';
        }
        return $this->response->json($result);
    }


    /**
     * @DOC 平台配置
     */
    #[RequestMapping(path: 'api/platform/cfg', methods: 'post')]
    public function Cfg(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '添加失败';
        $param          = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'platform_id'   => ['required'],
                'data'          => ['required'],
                'platform_code' => ['required'],
            ], [
                'platform_id.required'   => '接口平台不存在',
                'platform_code.required' => '平台编码必填',
                'data.required'          => '配置信息必填',
            ]);

        $BeItemIDArr = [];//记录已经存在的数据
        //判断当前第三方平台是否存在
        $where['platform_id'] = $param['platform_id'];

        $platformData = ApiPlatformModel::with(['item'])->where($where)->first();

        if (empty($platformData)) {
            throw new HomeException('错误：当前第三方平台不存在');
        }
        $platformData = $platformData->toArray();
        //判断请求的字段是否存在
        $be_item_filed = [];
        if (Arr::hasArr($platformData, 'item')) {
            $be_item_filed = array_column($platformData['item'], 'item_filed');
        }

        if (Arr::hasArr($platformData, 'item')) {
            $BeItemIDArr = array_column($platformData['item'], 'item_id');
        }
        $time = time();
        //处理需要编辑的数据
        foreach ($param['data'] as $key => $item) {
            $LibValidation->validate($item,
                [
                    'item_id'      => ['nullable', 'integer'],
                    'item_filed'   => ['required'],
                    'item_name'    => ['required'],
                    'item_value'   => ['required'],
                    'member_write' => ['required'],
                    'status'       => ['required'],
                ], [
                    'item_id.integer'       => '配置信息必须数值',
                    'item_filed.required'   => '字段名必填',
                    'item_name.required'    => '字段中文名必填',
                    'item_value.required'   => '字段值必填',
                    'member_write.required' => '是否客户填写',
                    'status.required'       => '状态错误',
                ]);

            if (Arr::hasArr($item, 'item_id') && in_array($item['item_id'], $BeItemIDArr)) {
                $updateItem[$key]['item_id']      = $item['item_id'];
                $updateItem[$key]['platform_id']  = $platformData['platform_id'];
                $updateItem[$key]['item_filed']   = $item['item_filed'];
                $updateItem[$key]['item_name']    = $item['item_name'];
                $updateItem[$key]['item_value']   = $item['item_value'];
                $updateItem[$key]['member_write'] = $item['member_write'];
                $updateItem[$key]['status']       = $item['status'];
            } else {
                //判断新增的字符串 在表中是否存在
                if (in_array($item['item_filed'], $be_item_filed)) {
                    throw new HomeException('错误：平台 ' . $platformData['platform_name'] . ' ' . $item['item_filed'] . '已存在');
                }
                $insertItem[$key]['platform_id']  = $platformData['platform_id'];
                $insertItem[$key]['item_filed']   = $item['item_filed'];
                $insertItem[$key]['item_name']    = $item['item_name'];
                $insertItem[$key]['item_value']   = $item['item_value'];
                $insertItem[$key]['add_time']     = $time;
                $insertItem[$key]['member_write'] = $item['member_write'];
                $insertItem[$key]['status']       = $item['status'];
            }
        }
        Db::beginTransaction();
        try {
            if (!empty($updateItem)) {
                $updateBrandDataSql = batchUpdateSql('api_platform_item', $updateItem, ['item_id']);
                Db::update($updateBrandDataSql);
            }
            //新增
            if (!empty($insertItem)) {
                Db::table('api_platform_item')->insert($insertItem);
            }
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '编辑成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }

}
