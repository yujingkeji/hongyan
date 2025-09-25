<?php

declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\ApiPlatformInterfaceModel;
use App\Model\CategoryModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function App\Common\batchUpdateSql;

#[Controller(prefix: '/', server: 'httpAdmin')]
class InterfacesController extends AdminBaseController
{

    /**
     * @DOC 业务接口列表
     */
    #[RequestMapping(path: 'api/interfaces/lists', methods: 'post')]
    public function lists(RequestInterface $request): ResponseInterface
    {
        $param = $request->all();
        $data  = ApiPlatformInterfaceModel::query();
        if (Arr::hasArr($param, 'keyword')) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('interface_name', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('interface_method', 'like', '%' . $param['keyword'] . '%');
            });
        }
        if (Arr::hasArr($param, 'platform_id')) {
            $data = $data->where('platform_id', $param['platform_id']);
        }
        if (Arr::hasArr($param, 'interface_cfg_id')) {
            $data = $data->where('interface_cfg_id', $param['interface_cfg_id']);
        }

        $data = $data->with(
            [
                'platform', 'auth', 'auth.element'
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
    #[RequestMapping(path: 'api/interfaces/add', methods: 'post')]
    public function handleAdd(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'platform_id'      => ['required'],
                'interface_cfg_id' => ['required'],
                'interface_name'   => ['required'],
                'interface_code'   => ['required'],
                'interface_method' => ['required'],
                //                'docking_status'   => ['required'],
            ], [
                'platform_id.required'      => '第三方接口平台必填',
                'interface_cfg_id.required' => '接口类型ID必填',
                'interface_name.required'   => '接口名称必填',
                'interface_code.required'   => '接口编码必填',
                'interface_method.required' => '具体接口必填',
                //                'docking_status.required'   => '对接状态必填',
            ]);


        $where[] = ['platform_id', '=', $param['platform_id']];

        if (Arr::hasArr($param, 'interface_code')) $where[] = ['interface_code', '=', strtolower($param['interface_code'])];

        $data = ApiPlatformInterfaceModel::where($where)->exists();
        if (!empty($data)) {
            throw new HomeException('添加失败：该接口已存在');
        }
        $param['interface_code'] = strtolower($param['interface_code']);
        $param['add_time']       = time();
        if (ApiPlatformInterfaceModel::insert($param)) {
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 调整状态
     */
    #[RequestMapping(path: 'api/interfaces/status', methods: 'post')]
    public function handleStatus(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'interface_id'   => ['required'],
                'status'         => ['required'],
                'interface_code' => ['required'],
            ], [
                'interface_id.required'   => '业务接口不存在',
                'status.required'         => '状态错误',
                'interface_code.required' => '接口编码错误',
            ]);
        $where[] = ['interface_id', '=', $param['interface_id']];
        $data    = ApiPlatformInterfaceModel::where($where)->first();
        if (empty($data)) {
            throw new HomeException('编辑失败：该接口数据不存在');
        }
        if ($param['interface_code'] != $data['interface_code']) {
            throw new HomeException('修改失败：当前接口代码不一致');
        }

        if (ApiPlatformInterfaceModel::where('interface_id', $param['interface_id'])
            ->update(['status' => $param['status']])) {
            $result['code'] = 200;
            $result['msg']  = '编辑成功';
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 更新
     */
    #[RequestMapping(path: 'api/interfaces/edit', methods: 'post')]
    public function handleEdit(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $param          = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'interface_id'     => ['required'],
                'platform_id'      => ['required'],
                'interface_cfg_id' => ['required'],
                'interface_name'   => ['required'],
                'interface_code'   => ['required'],
                'interface_method' => ['required'],
                //                'docking_status'   => ['required'],
            ], [
                'interface_id.required'     => '业务接口不存在',
                'platform_id.required'      => '第三方接口平台必填',
                'interface_cfg_id.required' => '接口类型ID必填',
                'interface_name.required'   => '接口名称必填',
                'interface_code.required'   => '接口编码必填',
                'interface_method.required' => '具体接口必填',
                //                'docking_status.required'   => '对接状态必填',
            ]);
        $where[] = ['interface_id', '=', $param['interface_id']];
        $data    = ApiPlatformInterfaceModel::where($where)->first();
        if (empty($data)) {
            throw new HomeException('编辑失败：该接口不存在');
        }
        unset($where);
        $where[] = ['interface_code', '=', strtolower($param['interface_code'])];
        $where[] = ['platform_id', '=', $param['platform_id']];
        $data    = ApiPlatformInterfaceModel::where($where)->first();
        if (!empty($data) && $param['interface_id'] != $data['interface_id']) {
            throw new HomeException('编辑失败：同平台下 interface_code 不能重复');
        }

        if (ApiPlatformInterfaceModel::where('interface_id', $param['interface_id'])->update($param)) {
            $result['code'] = 200;
            $result['msg']  = '编辑成功';
        }
        return $this->response->json($result);
    }


    /**
     * @DOC 更新认证要素
     */
    #[RequestMapping(path: 'api/interfaces/auth', methods: 'post')]
    public function Auth(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '添加失败';
        $param          = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'interface_id'   => ['required'],
                'data'           => ['required'],
                'interface_code' => ['required'],
            ], [
                'interface_id.required'   => '业务接口不存在',
                'interface_code.required' => '接口编码必填',
                'data.required'           => '认证要素必填',
            ]);
        //判断当前第三方平台是否存在
        $where['interface_id'] = $param['interface_id'];
        $interfaceData         = ApiPlatformInterfaceModel::with(['auth'])->where($where)->first();
        if (empty($interfaceData)) {
            throw new HomeException('错误：当前业务接口不存在');
        }
        $interfaceData = $interfaceData->toArray();
        $platform_id   = $interfaceData['platform_id'];//平台ID
        $interface_id  = $interfaceData['interface_id'];//接口ID
        //判断请求的字段是否存在
        $beAuthCfgArr = [];
        if (Arr::hasArr($interfaceData, 'auth')) {
            $beAuthCfgArr = array_column($interfaceData['auth'], 'auth_cfg_id');
        }
        $beAuthIDArr = [];
        //判断那些ID已经存在，存在，就不用添加，只用更新
        if (Arr::hasArr($interfaceData, 'auth')) {
            $beAuthIDArr = array_column($interfaceData['auth'], 'auth_id');
        }

        //所有认证要素
        $elementDb = CategoryModel::where('pid', '=', '1700')->get()->toArray();

        $elementData = array_column($elementDb, null, 'cfg_id');
        $time        = time();
        //处理需要编辑的数据
        $delItem    = $beAuthIDArr;
        $updateItem = $insertItem = [];
        foreach ($param['data'] as $key => $item) {
            $LibValidation->validate($item,
                [
                    'auth_id'     => ['nullable', 'integer'],
                    'auth_cfg_id' => ['required'],
                    'status'      => ['required'],
                ], [
                    'auth_id.integer'      => '认证要素必须数值',
                    'auth_cfg_id.required' => '认证要素配置必填',
                    'status.required'      => '状态错误',
                ]);

            if (isset($elementData[$item['auth_cfg_id']]) && Arr::hasArr($elementData[$item['auth_cfg_id']], 'code')) {
                $auth_cfg_name = $elementData[$item['auth_cfg_id']]['title'];
            } else {
                throw new HomeException('错误：认证要素不存在');
            }
            if (Arr::hasArr($item, 'auth_id') && in_array($item['auth_id'], $beAuthIDArr)) {
                $updateItem[$key]['auth_id']       = $item['auth_id'];
                $updateItem[$key]['status']        = $item['status'];
                $updateItem[$key]['auth_cfg_id']   = $item['auth_cfg_id'];
                $updateItem[$key]['auth_cfg_name'] = $auth_cfg_name;
                $updateItem[$key]['platform_id']   = $platform_id;
                $updateItem[$key]['interface_id']  = $interface_id;
                Arr::del($delItem, $item['auth_id']);
            } else {
                //判断新增的字符串 在表中是否存在
                if (in_array($item['auth_cfg_id'], $beAuthCfgArr)) {
                    throw new HomeException('错误：认证要素 ' . $auth_cfg_name . ' 已存在');
                }
                $insertItem[$key]['auth_cfg_id']   = $item['auth_cfg_id'];
                $insertItem[$key]['auth_cfg_name'] = $auth_cfg_name;
                $insertItem[$key]['platform_id']   = $platform_id;
                $insertItem[$key]['interface_id']  = $interface_id;
                $insertItem[$key]['add_time']      = $time;
                $insertItem[$key]['status']        = $item['status'];
            }
        }
        Db::beginTransaction();
        try {
            if (!empty($updateItem)) {
                $updateBrandDataSql = batchUpdateSql('api_platform_interface_auth', $updateItem,['auth_id']);
                Db::update($updateBrandDataSql);
            }
            //新增
            if (!empty($insertItem)) {
                Db::table('api_platform_interface_auth')->insert($insertItem);
            }
            if (!empty($delItem)) {
                Db::table('api_platform_interface_auth')->whereIn('auth_id', $delItem)->delete();
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
