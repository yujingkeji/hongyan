<?php

namespace App\Controller\Home\Chat;

use App\Controller\Home\HomeBaseController;
use App\Model\ParcelModel;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: "chat/order")]
class OrderController extends HomeBaseController
{

    /**
     * @DOC 获取用户信息
     */
    #[RequestMapping(path: 'info', methods: 'post')]
    public function info(RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $param = $request->all();

        $parcel = ParcelModel::query()
            ->with([
                'ware'        => function ($query) {
                    $query->select(['ware_id', 'ware_name']);
                },
                'line'        => function ($query) {
                    $query->select(['line_id', 'line_name']);
                },
                'channel'     => function ($query) {
                    $query->select(['channel_id', 'channel_name']);
                },
                'receiver',
                'item',
                'cost_member' => function ($query) {
                    $query->select(['order_sys_sn', 'member_join_weight', 'length', 'width', 'height']);
                }
            ])->where('order_sys_sn', $param['waybill_no'])->first();
        if ($parcel) {
            $parcel = $parcel->toArray();
            // 收件人解密
            $receiver = $this->addressDecrypt($parcel['receiver']);
            // 收件人拼接
            $receiverData = [
                'name'     => $receiver['name'], // 名称
                'tel'      => $receiver['mobile'], // 手机号
                'country'  => $receiver['country'], // 国家
                'state'    => $receiver['province'], // 省
                'city'     => $receiver['city'], // 市
                'district' => $receiver['district'], // 区
                'street'   => $receiver['street'], // 街道
                'address'  => $receiver['country'] . $parcel['receiver']['province'] . $parcel['receiver']['city'] . $parcel['receiver']['district'] . $parcel['receiver']['street'] . $parcel['receiver']['address'], // 详细地址
            ];

            // 商品
            $goods = [];
            foreach ($parcel['item'] as $k => $v) {
                $goods[$k]['name']      = $v['item_sku_name'];
                $goods[$k]['count']     = $v['item_num'];
                $goods[$k]['price']     = $v['item_price'];
                $goods[$k]['amount']    = $v['item_total'];
                $goods[$k]['goods_url'] = '';
            }

            // 数据拼接
            $data = [
                'head_url'      => '', // 商品头像
                'order_no'      => $parcel['order_sys_sn'], // 订单号
                'tp_waybill_no' => $parcel['transport_sn'], // 运单号
                'line'          => $parcel['line']['line_name'] ?? '', // 转运线路
                'transport'     => $parcel['channel']['channel_name'] ?? '', // 运输方式
                'channel'       => $parcel['channel']['channel_name'] ?? '', // 走件渠道
                'ware'          => $parcel['ware']['ware_name'] ?? '', // 仓库
                'weight'        => $parcel['cost_member']['member_join_weight'] ?? 0 . 'KG',  // 出库重量
                'volume'        => ($parcel['cost_member']['length'] ?? 0 * $parcel['cost_member']['height'] ?? 0 * $parcel['cost_member']['width'] ?? 0) . 'cm³',  // 出库体积
                'desc'          => $parcel['desc'], // 订单备注
                'receiver'      => $receiverData, // 收件人
                'goods'         => $goods, // 商品
                'route'         => [], // 路由
            ];
            return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);
        }
        return $this->response->json(['code' => 201, 'msg' => '包裹未查到', 'data' => []]);

    }


}
