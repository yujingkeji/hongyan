<?php
/**
 * 备案处理
 * *****************************************************************
 * 这不是一个自由软件,谢绝修改再发布.
 * @Created by PhpStorm.
 * @Name    :   Auth.php
 * @Email   :   28386631@qq.com
 * @Author  :   wangfei
 * @Date    :   2023-04-17 11:24
 * @Link    :   http://ServPHP.LinkUrl.cn
 * *****************************************************************
 */

namespace App\Controller\Home\Orders;


use App\Exception\HomeException;
use App\JsonRpc\RecordServiceInterface;
use App\Model\DeliveryStationModel;
use App\Model\GoodsSkuModel;
use App\Model\OrderModel;
use App\Service\Cache\BaseCacheService;
use App\Service\PriceVersionCalcService;
use App\Service\ThirdService\Third\record;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use function App\Common\batchUpdateSql;


#[Controller(prefix: 'orders/record')]
class RecordController extends OrderBaseController
{
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    #[Inject]
    protected BaseCacheService $baseCacheService;


    /**
     * @DOC   : 绑定备案:绑定个人自己商品备案
     * @Name  : bind
     * @Author: wangfei
     * @date  : 2023-05-05 2023
     * @param Request $request
     */
    #[RequestMapping(path: 'bind', methods: 'get,post')]
    public function bind(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '绑定失败';
        $member         = $request->UserInfo;
        $params         = $request->all();
        $validator      = $this->validationFactory->make(
            $params,
            [
                'order_sys_sn' => ['required', 'string'],
                'item_id'      => ['required', 'integer'],
                'sku_code'     => ['string'],
            ],
            [
                'order_sys_sn.required' => 'order_sys_sn  must be required',
                'order_sys_sn.string'   => 'order_sys_sn  must be string',
                'item_id.required'      => 'item_id  must be required',
                'item_id.integer'       => 'item_id  must be integer',
                'sku_code.required'     => 'sku_code  must be required',
                'sku_code.string'       => 'sku_code  must be string',
                'sku_code.min'          => 'sku_code.min size of :attribute must be :min',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $params  = $validator->validated();
        $OrderDb = OrderModel::query()
            ->where('order_sys_sn', '=', $params['order_sys_sn'])
            ->select(['order_sys_sn', 'order_status'])
            ->first();

        if (empty($OrderDb)) {
            throw new HomeException('订单：' . $params['order_sys_sn'] . ' 不存在');
        }
        $OrderDb = $OrderDb->toArray();
        if ($OrderDb['order_status'] >= 30) {
            throw new HomeException('已转包、禁止修改');
        }
        unset($where);

        $skuWhere['sku_code']         = $params['sku_code'];
        $skuWhere['parent_agent_uid'] = $member['parent_agent_uid'];
        $skuData                      = GoodsSkuModel::query()->where($skuWhere)->first();
        if (empty($skuData)) {
            throw new HomeException('未查询到：' . $params['sku_code'] . ' 备案商品');
        }
        $skuData                       = $skuData->toArray();
        $itemSkuData['item_record_sn'] = $skuData['sku_code'];
        $itemSkuData['sku_id']         = $skuData['sku_id'];

        $itemWhere                     = [];
        $itemWhere['item_id']          = $params['item_id'];
        $itemWhere['order_sys_sn']     = $params['order_sys_sn'];
        $itemWhere['parent_agent_uid'] = $member['parent_agent_uid'];


        unset($skuWhere);
        Db::beginTransaction();
        try {
            Db::table("order_item")->where($itemWhere)->update($itemSkuData);
            Db::table("order_exception_item")->where('order_sys_sn', '=', $params['order_sys_sn'])
                ->where('code', '=', $this->importRecord)->delete();
            //当需要绑定的数据为0 得时候，备案状态：为备案通过。
            $itemCount = Db::table('order_item')->where('order_sys_sn', '=', $params['order_sys_sn'])
                ->where('sku_id', '=', 0)->count();
            if ($itemCount == 0) {
                $OrderUpdate['order_record'] = 22006;
            }
            $count = Db::table("order_exception_item")->where('order_sys_sn', '=', $params['order_sys_sn'])->count();
            if ($count == 0) {
                $OrderUpdate['order_status'] = 28;
                Db::table("order_exception")->where('order_sys_sn', '=', $params['order_sys_sn'])->delete();
            }
            if (!empty($OrderUpdate)) {
                Db::table("order")->where('order_sys_sn', '=', $params['order_sys_sn'])->update($OrderUpdate);
            }
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '绑定成功';
        } catch (\Throwable $e) {
            $result['msg'] = $e->getMessage();
            Db::rollBack();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   : 绑定备案:绑定官方备案库
     * @Name  : bind
     * @Author: wangfei
     * @date  : 2023-05-05 2023
     * @param Request $request
     */
    #[RequestMapping(path: 'bind/official', methods: 'get,post')]
    public function bindOfficial(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '绑定失败';
        $member         = $request->UserInfo;
        $params         = $request->all();
        $validator      = $this->validationFactory->make(
            $params,
            [
                'order_sys_sn'  => ['required', 'string'],
                'item_id'       => ['required', 'integer'],
                'record_sku_id' => ['required', 'integer'],
                'sku_code'      => ['string'],
            ],
            [
                'order_sys_sn.required'  => 'order_sys_sn  must be required',
                'order_sys_sn.string'    => 'order_sys_sn  must be string',
                'item_id.required'       => 'item_id  must be required',
                'item_id.integer'        => 'item_id  must be integer',
                'record_sku_id.required' => 'record_sku_id  must be required',
                'record_sku_id.integer'  => 'record_sku_id  must be integer',
                'sku_code.string'        => 'sku_code  must be string',
            ]
        );
        if ($validator->fails()) {
            throw new HomeException($validator->errors()->first(), 201);
        }
        $params  = $validator->validated();
        $OrderDb = OrderModel::query()
            ->with([
                'prediction' => function ($query) {
                    $query->select(['order_sys_sn', 'parcel_type']);
                }
            ])
            ->where('order_sys_sn', '=', $params['order_sys_sn'])
            ->select(['order_sys_sn', 'order_status'])
            ->first();

        if (empty($OrderDb)) {
            throw new HomeException('订单：' . $params['order_sys_sn'] . ' 不存在');
        }
        $OrderDb = $OrderDb->toArray();
        if ($OrderDb['order_status'] >= 30 && !empty($OrderDb['prediction'] && $OrderDb['prediction'][0]['parcel_type'] == DeliveryStationModel::TYPE_DIRECT)) {
            throw new HomeException('已转包、禁止修改');
        }
        unset($where);

        // 查询官方备案库
        $thirdData = \Hyperf\Support\make(RecordServiceInterface::class)->batchBySkuId([$params['record_sku_id']]);
        if (!isset($thirdData['code']) || $thirdData['code'] != 200 || empty($thirdData['data'])) {
            throw new HomeException('未查询到官方备案商品');
        }
        $item_record_sn = empty($params['sku_code']) ? $params['record_sku_id'] : $params['sku_code'];
        // 判断当前用户是否存在当前备案关系
        $goods_record_data = [];
        $record_sku_id     = Db::table('goods_convert_record')
            ->where('item_code', $item_record_sn)
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('parent_join_uid', $member['parent_join_uid'])
            ->where('member_uid', $member['uid'])
            ->exists();
        if (!$record_sku_id) {
            $goods_record_data = [
                'parent_agent_uid' => $member['parent_agent_uid'],
                'parent_join_uid'  => $member['parent_join_uid'],
                'member_uid'       => $member['uid'],
                'item_code'        => $item_record_sn,
            ];
        }

        $itemSkuData['item_record_sn'] = $item_record_sn;
        $itemSkuData['sku_id']         = 0;

        $itemWhere                     = [];
        $itemWhere['item_id']          = $params['item_id'];
        $itemWhere['order_sys_sn']     = $params['order_sys_sn'];
        $itemWhere['parent_agent_uid'] = $member['parent_agent_uid'];

        unset($skuWhere);
        Db::beginTransaction();
        try {
            Db::table("order_item")->where($itemWhere)->update($itemSkuData);
            if (!empty($goods_record_data)) {
                Db::table("goods_convert_record")->insert($goods_record_data);
            }
            Db::table("order_exception_item")->where('order_sys_sn', '=', $params['order_sys_sn'])
                ->where('code', '=', $this->importRecord)->delete();
            //当需要绑定的数据为0 得时候，备案状态：为备案通过。
            $itemCount = Db::table('order_item')->where('order_sys_sn', '=', $params['order_sys_sn'])
                ->where('sku_id', '=', 0)->count();
            if ($itemCount == 0) {
                $OrderUpdate['order_record'] = 22006;
            }
            $count = Db::table("order_exception_item")->where('order_sys_sn', '=', $params['order_sys_sn'])->count();
            if ($count == 0) {
                $OrderUpdate['order_status'] = 28;
                if ($OrderDb['prediction'] && $OrderDb['prediction'][0]['parcel_type'] == DeliveryStationModel::TYPE_COLLECT) {
                    $OrderUpdate['order_status'] = 30;
                }
                Db::table("order_exception")->where('order_sys_sn', '=', $params['order_sys_sn'])->delete();
            }
            if (!empty($OrderUpdate)) {
                Db::table("order")->where('order_sys_sn', '=', $params['order_sys_sn'])->update($OrderUpdate);
            }
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '绑定成功';
        } catch (\Throwable $e) {
            $result['msg'] = $e->getMessage();
            Db::rollBack();
        }
        return $this->response->json($result);
    }


}
