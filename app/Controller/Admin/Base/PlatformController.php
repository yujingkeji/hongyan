<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Common\Lib\Str;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\AdminLogModel;
use App\Model\PlatformConfigModel;
use App\Request\LibValidation;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use function App\Common\batchUpdateSql;


#[Controller(prefix: '/', server: 'httpAdmin')]
class PlatformController extends AdminBaseController
{
    protected $target_table = "platform_config";

    /**
     * @DOC 配置管理列表
     */
    #[RequestMapping(path: 'base/platform/config/lists', methods: 'post')]
    public function configLists(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $param          = $request->all();

        $data = PlatformConfigModel::query()->with(['item', 'group'])
            ->where('delete_time', 0);

        if (Arr::hasArr($param, 'group_id')) {
            $data = $data->where('group_id', '=', $param['group_id']);
        }
        if (Arr::hasArr($param, 'keyword')) {
            $data = $data->where(function ($query) use ($param) {
                $query->orWhere('code', 'like', '%' . $param['keyword'] . '%')
                    ->orWhere('name', 'like', '%' . $param['keyword'] . '%');
            });
        }
        if (Arr::hasArr($param, 'start_time')) {
            $data = $data->where('add_time', '>=', $param['start_time']);
        }
        if (Arr::hasArr($param, 'end_time')) {
            $data = $data->where('add_time', '<=', $param['end_time']);
        }

        $data = $data->paginate($param['list_rows'] ?? 20);


        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'data'  => $data->items(),
            'total' => $data->total(),
        ];

        return $this->response->json($result);
    }

    /**
     * @DOC 配置管理列表
     */
    #[RequestMapping(path: 'base/platform/config/info', methods: 'post')]
    public function configInfo(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '获取失败';
        $params         = $request->all();

        $LibValidation  = \Hyperf\Support\make(LibValidation::class);
        $param          = $LibValidation->validate($params,
            [
                'platform_id' => ['required'],
            ], [
                'platform_id.required' => '配置错误',
            ]);
        $data           = PlatformConfigModel::with(
            ['item', 'group']
        )->where('platform_id', $param['platform_id'])->first();
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $data ? $data->toArray() : [];

        return $this->response->json($result);
    }

    /**
     * @DOC 添加审核配置
     */
    #[RequestMapping(path: 'base/platform/config/add', methods: 'post')]
    public function configAdd(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        //验证输入的第一级数据
        $param = $LibValidation->validate($request->all(),
            [
                'code'              => ['required', 'min:5', 'max:15', Rule::unique('platform_config', 'code')],
                'name'              => ['required', 'min:2'],
                'info'              => ['min:1'],
                'item'              => ['required', 'array'],
                'group_id'          => ['required'],
                'status'            => ['required', Rule::in([0, 1])],
                'item.*.item_name'  => ['required', 'min:2'],
                'item.*.item_value' => ['required'],
                'item.*.info'       => ['min:1'],
            ], [
                'code.required'              => '配置代码必填',
                'code.min'                   => '配置代码不少于5位',
                'code.max'                   => '配置代码不大于15位',
                'code.unique'                => '配置代码已存在',
                'name.required'              => '配置名称必填',
                'name.min'                   => '配置名称不少于2位',
                'status.required'            => '状态错误',
                'status.in'                  => '状态错误',
                'group_id.required'          => '分组必选',
                'item.required'              => '配置必填',
                'item.array'                 => '配置格式错误，必须为数组',
                'item.*.item_name.required'  => '节点名称必填',
                'item.*.item_name.min'       => '节点名称不少于2位',
                'item.*.item_value.required' => '节点配置必填',
                'item.*.info.min'            => '节点描述不少于1位',
            ]);

        // 准备插入的数据
        $handlePlatformData = $this->handlePlatformData($param);

        //开始数据保存
        Db::beginTransaction();
        try {
            // 插入平台配置
            $platform_id = $this->insertPlatformConfig($handlePlatformData);
            $this->insertItems($param['item'], $platform_id);
            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '添加成功';
            // 记录日志
            $this->logAction($request->UserInfo, $platform_id, '添加配置');
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }

        return $this->response->json($result);
    }

    /**
     * 插入平台配置
     * @param array $flow
     * @return int
     */
    protected function insertPlatformConfig(array $flow): int
    {
        return Db::table('platform_config')->insertGetId($flow);
    }


    /**
     * handlePlatformData
     * 准备插入的平台配置数据
     * @param array $param
     * @return array
     */
    protected function handlePlatformData(array $param): array
    {
        $time = time();
        return [
            'name'     => $param['name'],
            'code'     => Str::lower($param['code']),
            'group_id' => $param['group_id'],
            'add_time' => $time,
            'status'   => $param['status'],
            'info'     => $param['info'] ?? '',
        ];
    }

    /**
     * @DOC 编辑线路
     */
    #[RequestMapping(path: 'base/platform/config/edit', methods: 'post')]
    public function configEdit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        try {
            // 验证输入的第一级数据
            $param = $this->validateParams($params);

            // 获取平台配置数据
            $platformData = $this->getPlatformData($param['platform_id']);

            // 检查配置是否锁定
            $this->checkLockStatus($platformData);

            // 获取现有项和需要删除的项
            [$BeItemIDArr, $DeItemArr] = $this->getExistingAndDeletableItems($platformData);

            // 验证输入的第二级数据
            $this->validateItems($param['item']);

            // 准备更新和插入的数据
            [$updateItem, $insertItem, $DeItemArr] = $this->prepareUpdateAndInsertData($param['item'], $BeItemIDArr, $DeItemArr, $platformData['platform_id']);

            // 开始事务
            Db::beginTransaction();
            try {
                // 更新平台配置
                $this->updatePlatformConfig($param['platform_id'], $param);
                // 更新、插入和删除项
                $this->updateItems($updateItem);
                $this->insertItems($insertItem, $param['platform_id']);
                $this->deleteItems($DeItemArr, $platformData['platform_id']);

                // 提交事务
                Db::commit();
                $result['code'] = 200;
                $result['msg']  = '编辑成功';

                // 记录日志
                $this->logAction($request->UserInfo, $platformData['platform_id'], '编辑配置');
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                $result['msg'] = $e->getMessage();
            }
        } catch (HomeException $e) {
            $result['msg'] = $e->getMessage();
        }

        return $this->response->json($result);
    }

    /**
     * 验证输入的第一级数据
     * @param array $params
     * @return array
     * @throws HomeException
     */
    protected function validateParams(array $params): array
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        return $LibValidation->validate($params, [
            'platform_id' => ['required', Rule::exists('platform_config', 'platform_id')],
            'name'        => ['required', 'min:2'],
            'info'        => ['min:1'],
            'item'        => ['required', 'array'],
            'group_id'    => ['required'],
            'status'      => ['required', Rule::in([0, 1])],
        ], [
            'platform_id.required' => '配置代码不存在',
            'platform_id.exists'   => '配置代码不存在',
            'name.required'        => '配置名称必填',
            'name.min'             => '配置名称不少于2位',
            'status.required'      => '状态错误',
            'status.in'            => '状态错误',
            'group_id.required'    => '分组必选',
            'item.required'        => '配置必填',
            'item.array'           => '配置格式错误，必须为数组',
        ]);
    }

    /**
     * 获取平台配置数据
     * @param int $platformId
     * @return array
     * @throws HomeException
     */
    protected function getPlatformData(int $platformId): array
    {
        $platformData = PlatformConfigModel::with(['item'])
            ->where('platform_id', $platformId)
            ->first()
            ->toArray();

        if (empty($platformData)) {
            throw new HomeException('当前配置不存在');
        }

        return $platformData;
    }

    /**
     * 检查配置是否锁定
     * @param array $platformData
     * @throws HomeException
     */
    protected function checkLockStatus(array $platformData): void
    {
        if ($platformData['lock'] == 1) {
            throw new HomeException('禁止编辑：当前配置已锁定', 201);
        }
    }

    /**
     * 获取现有项和需要删除的项
     * @param array $platformData
     * @return array
     */
    protected function getExistingAndDeletableItems(array $platformData): array
    {
        $BeItemIDArr = $DeItemArr = [];
        if (Arr::hasArr($platformData, 'item')) {
            $BeItemIDArr = array_column($platformData['item'], 'item_id');
            $DeItemArr   = $BeItemIDArr;
        }
        return [$BeItemIDArr, $DeItemArr];
    }

    /**
     * 验证输入的第二级数据
     * @param array $items
     * @throws HomeException
     */
    protected function validateItems(array $items): void
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        foreach ($items as $item) {
            $LibValidation->validate($item, [
                'item_name'  => ['required', 'min:2'],
                'item_value' => ['required'],
            ], [
                'item_name.required'  => '节点名称必填',
                'item_name.min'       => '节点名称不少于2位',
                'item_value.required' => '节点配置必填',
            ]);
        }
    }

    /**
     * 准备更新和插入的数据
     * @param array $items
     * @param array $BeItemIDArr
     * @param array $DeItemArr
     * @param int $platformId
     * @return array
     */
    protected function prepareUpdateAndInsertData(array $items, array $BeItemIDArr, array $DeItemArr, int $platformId): array
    {
        $updateItem = $insertItem = [];
        foreach ($items as $key => $item) {
            if (Arr::hasArr($item, 'item_id') && in_array($item['item_id'], $BeItemIDArr)) {
                $updateItem[$key]['item_id']     = $item['item_id'];
                $updateItem[$key]['platform_id'] = $platformId;
                $updateItem[$key]['item_name']   = $item['item_name'];
                $updateItem[$key]['item_value']  = $item['item_value'];
                $updateItem[$key]['info']        = $item['info'];
                $DeItemArr                       = Arr::del($DeItemArr, $item['item_id']);
            } else {
                $insertItem[$key]['platform_id'] = $platformId;
                $insertItem[$key]['item_name']   = $item['item_name'];
                $insertItem[$key]['item_value']  = $item['item_value'];
                $insertItem[$key]['info']        = $item['info'];
            }
        }
        return [$updateItem, $insertItem, $DeItemArr];
    }

    /**
     * 更新平台配置
     * @param int $platformId
     * @param array $param
     */
    protected function updatePlatformConfig(int $platformId, array $param): void
    {
        Db::table('platform_config')
            ->where('platform_id', $platformId)
            ->update([
                'name'     => $param['name'],
                'status'   => $param['status'],
                'info'     => $param['info'] ?? '',
                'group_id' => $param['group_id'],
            ]);
    }

    /**
     * 更新项
     * @param array $updateItem
     */
    protected function updateItems(array $updateItem): void
    {
        if (!empty($updateItem)) {
            $updateBrandDataSql = batchUpdateSql('platform_config_item', $updateItem, ['item_id']);
            Db::update($updateBrandDataSql);
        }
    }

    /**
     * 插入项
     * @param array $insertItem
     */
    protected function insertItems(array $insertItem, int $platform_id): void
    {
        if (!empty($insertItem)) {
            $insertItem = array_map(function ($item) use ($platform_id) {
                return array_merge($item, ['platform_id' => $platform_id]);
            }, $insertItem);
            Db::table('platform_config_item')->insert($insertItem);
        }
    }

    /**
     * 删除项
     * @param array $DeItemArr
     * @param int $platformId
     */
    protected function deleteItems(array $DeItemArr, int $platformId): void
    {
        if (!empty($DeItemArr)) {
            Db::table('platform_config_item')
                ->where('platform_id', $platformId)
                ->whereIn('item_id', $DeItemArr)
                ->delete();
        }
    }

    /**
     * 记录操作日志
     * @param array $userInfo
     * @param int $platformId
     * @param string $action
     */
    protected function logAction(array $userInfo, int $platformId, string $action): void
    {
        $log_data['admin_uid']       = $userInfo['uid'];
        $log_data['target_table']    = $this->target_table;
        $log_data['target_table_id'] = $platformId;
        $log_data['add_time']        = time();
        $log_data['log_info']        = $userInfo['user_name'] . $action;
        AdminLogModel::insert($log_data);
    }

    /**
     * @DOC 状态修改
     */
    #[RequestMapping(path: 'base/platform/config/status', methods: 'post')]
    public function configStatus(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'platform_id' => ['required', Rule::exists('platform_config', 'platform_id')],
                'name'        => ['required', 'min:2'],
                'status'      => ['required', Rule::in([0, 1])],
            ], [
                'platform_id.required' => '配置代码不存在',
                'platform_id.exists'   => '配置代码不存在',
                'name.required'        => '配置名称必填',
                'name.min'             => '配置名称不少于2位',
                'status.required'      => '状态错误',
                'status.in'            => '状态错误',
            ]);

        $where['platform_id'] = $param['platform_id'];
        $platformData         = PlatformConfigModel::where($where)->first()->toArray();
        if ($platformData['name'] != $param['name']) {
            throw new HomeException('禁止修改状态：当前数据不匹配');
        }
        if ($platformData['lock'] == 1) {
            throw new HomeException('禁止修改状态：配置已锁定');
        }
        if (Db::table("platform_config")->where($where)->update(['status' => $param['status']])) {
            $result['code']               = 200;
            $result['msg']                = '处理成功';
            $log_data['admin_uid']        = $request->UserInfo['uid'];
            $log_data['target_table']     = $this->target_table;
            $log_data['target_table_id']  = $platformData['platform_id'];
            $log_data['target_table_val'] = $param['status'];
            $log_data['add_time']         = time();
            $log_data['log_info']         = $request->UserInfo['user_name'] . '调整状态为：' . $param['status'];
            AdminLogModel::insert($log_data);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   : 删除
     */
    #[RequestMapping(path: 'base/platform/config/del', methods: 'post')]
    public function configDel(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'platform_id' => ['required', Rule::exists('platform_config', 'platform_id')],
            ], [
                'platform_id.required' => '配置代码不存在',
                'platform_id.exists'   => '配置代码不存在',
            ]);

        $where['platform_id'] = $param['platform_id'];
        $platformData         = PlatformConfigModel::where($where)->first()->toArray();
        if ($platformData['lock'] == 1) {
            throw new HomeException('禁止删除：当前配置已锁定、禁止删除');
        }
        if (PlatformConfigModel::where($where)->update(['delete_time' => time()])) {
            $result['code']              = 200;
            $result['msg']               = '删除成功';
            $log_data['admin_uid']       = $request->UserInfo['uid'];
            $log_data['target_table']    = $this->target_table;
            $log_data['target_table_id'] = $platformData['platform_id'];
            $log_data['add_time']        = time();
            $log_data['log_info']        = $request->UserInfo['user_name'] . '删除操作';
            AdminLogModel::insert($log_data);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 配置日志
     */
    #[RequestMapping(path: 'base/platform/config/log', methods: 'post')]
    public function configLog(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();

        $LibValidation            = \Hyperf\Support\make(LibValidation::class);
        $param                    = $LibValidation->validate($params,
            [
                'platform_id' => ['required', Rule::exists('platform_config', 'platform_id')],
            ], [
                'platform_id.required' => '配置代码不存在',
                'platform_id.exists'   => '配置代码不存在',
            ]);
        $where['target_table']    = $this->target_table;
        $where['target_table_id'] = $param['platform_id'];

        $data = AdminLogModel::select(['log_id', 'log_info', 'add_time', 'target_table_val'])
            ->where($where)->orderBy('add_time', 'DESC')->get()->toArray();

        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = $data;
        return $this->response->json($result);
    }

    /**
     * @DOC 锁定配置
     */
    #[RequestMapping(path: 'base/platform/config/lock', methods: 'post')]
    public function configLock(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();

        $LibValidation        = \Hyperf\Support\make(LibValidation::class);
        $param                = $LibValidation->validate($params,
            [
                'platform_id' => ['required', Rule::exists('platform_config', 'platform_id')],
                'status'      => ['required', Rule::in([0, 1])],
                'lock'        => ['required', Rule::in([0, 1])],
            ], [
                'platform_id.required' => '配置代码不存在',
                'platform_id.exists'   => '配置代码不存在',
                'status.required'      => '状态错误',
                'status.in'            => '状态错误',
                'lock.required'        => '锁定状态错误',
                'lock.in'              => '锁定状态错误',
            ]);
        $where['platform_id'] = $param['platform_id'];
        $platformData         = PlatformConfigModel::where($where)->first()->toArray();
        if (empty($platformData)) {
            throw new HomeException('禁止修改状态：当前配置不存在');
        }
        if ($platformData['status'] != $param['status']) {
            throw new HomeException('禁止修改状态：当前数据不匹配');
        }
        if (PlatformConfigModel::where($where)->update(['lock' => $param['lock']])) {
            $result['code']               = 200;
            $result['msg']                = '处理成功';
            $log_data['admin_uid']        = $request->UserInfo['uid'];
            $log_data['target_table']     = $this->target_table;
            $log_data['target_table_id']  = $platformData['platform_id'];
            $log_data['target_table_val'] = $param['lock'];
            $log_data['add_time']         = time();
            $log_data['log_info']         = $request->UserInfo['user_name'] . '调整锁定值为：' . $param['lock'];
            AdminLogModel::insert($log_data);
        }

        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = [];
        return $this->response->json($result);
    }

}
