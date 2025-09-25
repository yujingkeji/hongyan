<?php
/**
 * 批量制单
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


use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Unique;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\CountryAreaModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\OrdersCreateCheckService;
use App\Service\PlatformService\Platform\yfd;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: 'orders/batch')]
class BatchController extends OrderBaseController
{
    #[Inject]
    protected BaseServiceInterface $baseService;

    /**
     * @DOC 获取批次号
     */
    #[RequestMapping(path: "sn", methods: "post")]
    public function sn(RequestInterface $request)
    {
        $result['code']           = 201;
        $result['msg']            = '获取失败';
        $member                   = $request->UserInfo;
        $Unique                   = new Unique();
        $batch_sn                 = $Unique->batch();
        $data['batch_sn']         = $batch_sn;
        $data['member_uid']       = $member['uid'];
        $data['parent_join_uid']  = $member['parent_join_uid'];
        $data['parent_agent_uid'] = $member['parent_agent_uid'];
        $data['batch_type']       = 1;
        $data['add_time']         = time();
        if (Db::table('order_batch')->insert($data)) {
            $data['batch_sn'] = $batch_sn;
            $result['code']   = 200;
            $result['msg']    = '获取成功';
            $result['data']   = ['batch_sn' => $batch_sn];
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 批次订单检查
     */
    #[RequestMapping(path: "check", methods: "post")]
    public function check(RequestInterface $request)
    {
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(OrdersCreateCheckService::class)->handle($request->all(), $member);
        return $this->response->json($result);
    }

    /**
     * @DOC   :分类检测
     * @Name  : categoryAnalyse
     * @Author: wangfei
     * @date  : 2025-04 15:45
     * @return \Psr\Http\Message\ResponseInterface
     *
     */
    #[RequestMapping(path: "category/analyse", methods: "post")]
    public function categoryAnalyse(RequestInterface $request)
    {
        $params = $request->all();
        $result = $this->baseService->categoryAnalyse($params);
        return $this->response->json($result);
    }

    /**
     * @DOC 批量制单
     */
    #[RequestMapping(path: "order", methods: "post")]
    public function order(RequestInterface $request)
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param,
            [
                'line_id'                          => ['required', 'integer'],
                'order_type'                       => ['required', 'integer'],
                'pro_id'                           => ['required', 'integer'],
                'batch_sn'                         => ['required'],
                'ware_id'                          => ['nullable', 'integer'], // 仓库ID
                'sender'                           => ['required', 'array'], // 发货人数组
                'sender.name'                      => ['required', 'string'], // 发货人
                'sender.address'                   => ['required', 'string'], // 发货人地址
                'sender.area_code'                 => ['required'], // 发货人手机区号
                'sender.mobile'                    => ['required', 'min:6'], // 发货人手机号
                'sender.phone'                     => ['nullable', 'min:6'], // 发货人手机号
                'sender.zip'                       => ['nullable', 'min:5'], // 发货人邮编
                'sender.country'                   => ['required', 'string'], // 发货人国家
                'sender.country_id'                => ['required', 'integer'], // 发货人国家ID
                'sender.province'                  => ['nullable', 'string'], // 发货人省份
                'sender.province_id'               => ['nullable', 'integer'], // 发货人省份ID
                'sender.city'                      => ['nullable', 'string'], // 发货人城市
                'sender.city_id'                   => ['nullable', 'integer'], // 发货人城市ID
                'sender.district'                  => ['nullable', 'string'], // 发货人镇/区
                'sender.district_id'               => ['nullable', 'integer'], // 发货人镇/区ID
                'sender.street'                    => ['nullable', 'string'], // 发货人街道
                'sender.street_id'                 => ['nullable', 'integer'], // 发货人街道ID
                'orders'                           => ['required', 'array'], // 订单信息
                'orders.*.user_custom_sn'          => ['required', 'string'], // 自定义编码
                'orders.*.from_order_sn'           => ['required', 'string'], // 来源单号
                'orders.*.from_platform_name'      => ['nullable', 'string'], // 订单来源
                'orders.*.desc'                    => ['nullable', 'string'], // 备注
                'orders.*.sender'                  => ['nullable', 'array'], // 详情发件信息
                'orders.*.sender.name'             => ['nullable', 'string'], // 详情发货人
                'orders.*.sender.address'          => ['nullable', 'string'], // 详情发货人地址
                'orders.*.sender.area_code'        => ['nullable'], // 详情发货人手机区号
                'orders.*.sender.mobile'           => ['nullable', 'min:6'], // 详情发货人手机号
                'orders.*.sender.phone'            => ['nullable', 'min:6'], // 详情发货人手机号
                'orders.*.sender.zip'              => ['nullable', 'min:5'], // 详情发货人邮编
                'orders.*.sender.country'          => ['nullable'], // 详情发货人国家
                'orders.*.sender.country_id'       => ['nullable', 'integer'], // 详情发货人国家ID
                'orders.*.sender.province'         => ['nullable'], // 详情发货人省份
                'orders.*.sender.province_id'      => ['nullable', 'integer'], // 详情发货人省份ID
                'orders.*.sender.city'             => ['nullable'], // 详情发货人城市
                'orders.*.sender.city_id'          => ['nullable', 'integer'], // 详情发货人城市ID
                'orders.*.sender.district'         => ['nullable'], // 详情发货人镇/区
                'orders.*.sender.district_id'      => ['nullable', 'integer'], // 详情发货人镇/区ID
                'orders.*.sender.street'           => ['nullable'], // 详情发货人街道
                'orders.*.sender.street_id'        => ['nullable', 'integer'], // 详情发货人街道ID
                'orders.*.receiver'                => ['required', 'array'], // 详情收件信息
                'orders.*.receiver.name'           => ['required', 'string'], // 收件人
                'orders.*.receiver.address'        => ['required', 'string'], // 收件人地址
                'orders.*.receiver.area_code'      => ['required'], // 收件人手机区号
                'orders.*.receiver.mobile'         => ['required', 'min:6'], // 收件人手机号
                'orders.*.receiver.phone'          => ['nullable', 'min:6'], // 收件人手机号
                'orders.*.receiver.zip'            => ['nullable', 'min:5'], // 收件人邮编
                'orders.*.receiver.country'        => ['required', 'string'], // 收件人国家
                'orders.*.receiver.country_id'     => ['required', 'integer'], // 收件人国家ID
                'orders.*.receiver.province'       => ['nullable'], // 收件人省份
                'orders.*.receiver.province_id'    => ['nullable', 'integer'], // 收件人省份ID
                'orders.*.receiver.city'           => ['nullable'], // 收件人城市
                'orders.*.receiver.city_id'        => ['nullable', 'integer'], // 收件人城市ID
                'orders.*.receiver.district'       => ['nullable'], // 收件人镇/区
                'orders.*.receiver.district_id'    => ['nullable', 'integer'], // 收件人镇/区ID
                'orders.*.receiver.street'         => ['nullable'], // 收件人街道
                'orders.*.receiver.street_id'      => ['nullable', 'integer'], // 收件人街道ID
                'orders.*.item'                    => ['required', 'array'], // 详情商品信息
                'orders.*.item.*.order_weight'     => ['nullable', 'integer', 'min:0'], // 商品总重量 第一行填了取第一行，若没有填，从上往下取，都没填返回必填
                'orders.*.item.*.brand_name'       => ['nullable', 'string'], // 商品品牌
                'orders.*.item.*.category_item'    => ['nullable', 'string'], // 商品分类名称  ///
                'orders.*.item.*.category_item_id' => ['nullable', 'integer'], // 商品分类名称ID  ///
                'orders.*.item.*.item_code'        => ['nullable', 'string'], // 商品用户自定义编码
                'orders.*.item.*.item_num'         => ['required', 'integer'], // 商品数量
                'orders.*.item.*.item_price'       => ['required', 'numeric'], // 商品价格
                'orders.*.item.*.item_price_unit'  => ['nullable', 'string'], // 商品币种
                'orders.*.item.*.item_sku'         => ['nullable', 'string'], // 商品SKU
                'orders.*.item.*.item_sku_name'    => ['nullable', 'string'], // 商品名称
                'orders.*.item.*.item_spec'        => ['required', 'string'], // 规格
            ],
            [
                'line_id.required'                         => '线路ID必填',
                'line_id.integer'                          => '线路ID错误',
                'order_type.required'                      => '订单类型必传',
                'order_type.integer'                       => '订单类型格式错误',
                'pro_id.required'                          => '产品类型必传',
                'pro_id.integer'                           => '产品类型格式错误',
                'batch_sn.required'                        => '批次号必传',
                'sender.required'                          => '请选择发货人',
                'orders.required'                          => '未检测到订单信息',
                'orders.*.item.required'                   => '商品信息必填',
                'orders.*.item.*.brand_name.required'      => '商品品牌必填',
                'orders.*.receiver.country.required'       => '收件国家必填',
                'orders.*.receiver.name.required'          => '收件人必填',
                'orders.*.receiver.address.required'       => '收件人详情地址必填',
                'orders.*.receiver.mobile.required'        => '收件人手机号码必填',
                'orders.*.receiver.area_code.required'     => '收件人手机区号必填',
                'orders.*.item.*.item_spec.required'       => '商品规格必填',
                'orders.*.item.*.item_price_unit.required' => '商品币种必填',
                'orders.*.item.*.item_price.required'      => '商品价格必填',
                'orders.*.item.*.item_num.required'        => '商品内置数量必填',
            ]
        );
        $orderData     = $orderItemData = $orderReceiverData = $orderSenderData = [];

        $batch_sn                   = $param['batch_sn']; //批次号
        $sender                     = $param['sender'];
        $sender['member_uid']       = $member['uid'];
        $sender['parent_join_uid']  = $member['parent_join_uid'];
        $sender['parent_agent_uid'] = $member['parent_agent_uid'];
        $sender['batch_sn']         = $batch_sn;
        $sender['order_sys_sn']     = 0;
        if ($this->checkSenderAddress($batch_sn, $member['uid'])) {
            $sender = $this->handleSenderAddress($sender);
        }

        $userCustomSnArr = array_column($param['orders'], 'user_custom_sn');
        $userCustomSnArr = Arr::delEmpty($userCustomSnArr);
        //检查当前自定义编码在库中是否存在
        $checkUserCustomSnInDb = $this->checkUserCustomSnInDb($userCustomSnArr, $member['uid']);
        if (!empty($checkUserCustomSnInDb)) {
            throw new HomeException("以下自定义单号已存在：" . implode(',', $checkUserCustomSnInDb));
        }

        //TODO 订单号
        $sys_time               = time();
        $UserDefinedIdGenerator = make(UserDefinedIdGenerator::class);
        // 获取订单来源所有信息
        $from_platform = \Hyperf\Support\make(BaseCacheService::class)->CategoryCache(1789, 0, ['cfg_id', 'title', 'sort']);
        $from_platform = array_column($from_platform, 'cfg_id', 'title');
        foreach ($param['orders'] as $key => $val) {
            $order_sys_sn              = $UserDefinedIdGenerator->generate($member['uid']); //系统订单号
            $order['order_sys_sn']     = $order_sys_sn;
            $order['line_id']          = $param['line_id'];
            $order['batch_sn']         = $batch_sn;
            $order['member_uid']       = $member['uid'];
            $order['parent_join_uid']  = $member['parent_join_uid'];
            $order['parent_agent_uid'] = $member['parent_agent_uid'];
            $order['order_status']     = 26;
            $order['add_time']         = $sys_time;
            $order['update_time']      = $sys_time;
            $order['order_type']       = $param['order_type'];
            // 来源
            $order['from_platform_id'] = $from_platform[$val['from_platform_name']] ?? 0;
            unset($val['from_platform_name']);
            $orderSingle = array_merge($order, $val);
            unset($order);

            $orderSingleData = $this->handle($orderSingle, $sender, $order_sys_sn, $batch_sn); //单个订单数据处理
            if (Arr::hasArr($orderSingleData, 'order')) {
                $orderData[] = $orderSingleData['order'];
            }

            if (Arr::hasArr($orderSingleData, 'receiver')) {
                $orderReceiverData[] = $orderSingleData['receiver'];
            }
            if (Arr::hasArr($orderSingleData, 'sender')) {
                $orderSenderData[] = $orderSingleData['sender'];
            }
            if (Arr::hasArr($orderSingleData, 'item')) {
                $orderItemData = array_merge($orderItemData, $orderSingleData['item']);
            }
        }
        //处理批次数据：
        unset($orderBatchWhere);
        $orderBatchWhere['batch_sn']   = $batch_sn;
        $orderBatchWhere['member_uid'] = $member['uid'];
        $orderTotal                    = count($orderData);

        Db::beginTransaction();
        try {
            Db::table("order")->insert($orderData);
            Db::table("order_batch")->where($orderBatchWhere)->update(['order_num' => Db::raw('order_num+' . $orderTotal)]);
            Db::table("order_sender")->insert($orderSenderData);
            Db::table("order_receiver")->insert($orderReceiverData);
            Db::table("order_item")->insert($orderItemData);
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '制单成功';
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $result['code'] = 201;
            $result['msg']  = '制单失败' . $e->getMessage();
        }
        return $this->response->json($result);
    }


    /**
     * @DOC 检测发件人批次地址是否存在
     */
    protected function checkSenderAddress(int $batch_sn, int $member_uid)
    {
        $where['member_uid']   = $member_uid;
        $where['batch_sn']     = $batch_sn;
        $where['order_sys_sn'] = 0;
        $SenderDb              = Db::table("order_sender")->where($where)->select(['sender_id'])->first();
        if (empty($SenderDb)) {
            return true;
        }
        return false;
    }

    protected function handleSenderAddress($sender)
    {
        $sender['country_id']  = CountryAreaModel::where('id', $sender['country_id'])->value('country_id');
        $sender['city']        = Arr::hasArr($sender, 'city') ? $sender['city'] : "";
        $sender['city_id']     = Arr::hasArr($sender, 'city_id') ? $sender['city_id'] : 0;
        $sender['district']    = Arr::hasArr($sender, 'district') ? $sender['district'] : "";
        $sender['district_id'] = Arr::hasArr($sender, 'district_id') ? $sender['district_id'] : 0;
        $sender['street']      = Arr::hasArr($sender, 'street') ? $sender['street'] : "";
        $sender['street_id']   = Arr::hasArr($sender, 'street_id') ? $sender['street_id'] : 0;
        $result['md5']         = $this->md5Address($sender);
        $result['sender']      = $sender;
        return $result;
    }

    /**
     * @DOC 检查用户自定义编码，在历史中是否存在
     */
    protected function checkUserCustomSnInDb(array $UserCustomSn, int $member_uid)
    {
        $where[] = ['member_uid', '=', $member_uid];
        $OrderDb = Db::table("order")
            ->select(['order_sys_sn', 'user_custom_sn'])
            ->where($where)
            ->whereIn('user_custom_sn', $UserCustomSn)
            ->get()->toArray();
        if (!empty($OrderDb)) {
            return array_column($OrderDb, 'user_custom_sn');
        }
        return [];
    }


    /**
     * @DOC   : 地址集合 去解析
     */
    protected function addressAnalyse(array $addressArr)
    {
        if (empty($addressArr)) {
            return [];
        }
        $batch = [];
        foreach ($addressArr as $k => $v) {
            if (!empty($v)) {
                $data['id'] = $v['md5'];
                $data['q']  = $v['address'];
                $batch[$k]  = $data;
            }
        }
        try {
            if (!empty($batch)) {
                $yfd          = new yfd();
                $yfd->method  = 'app.analyze.lists.address.get';
                $yfd->batch   = json_encode($batch, JSON_UNESCAPED_UNICODE);
                $yfd->country = 'cn';
                return $yfd->send('');
            }
        } catch (Exception $e) {
            return [];
        }

    }

    /**
     * @DOC   : 处理解析后的地址
     */
    protected function handleAddressAnalyse(array $addressArr)
    {
        $resultAddress = $this->addressAnalyse($addressArr);
        $response      = isset($resultAddress['response']) ? $resultAddress['response'] : [];
        if (!empty($response)) {
            foreach ($response as $key => $val) {
                $idArr       = $nameArr = $nameEnArr = [];
                $detail      = '';
                $countryArea = [];
                if (isset($val['total']) && $val['total'] == 1) {
                    $source = $val['source'];
                    if (Arr::hasArr($source, 'country')) {
                        $response[$key]['country']    = $source['country']['name'];
                        $response[$key]['country_en'] = $source['country']['name_en'];
                        $response[$key]['country_id'] = $source['country']['id'];
                        $countryArea['country']       = $source['country']['id'] . ',' . $source['country']['name'];
                    }
                    if (Arr::hasArr($source, 'province')) {
                        $response[$key]['province']    = $source['province']['name'];
                        $response[$key]['province_en'] = $source['province']['name_en'];
                        $response[$key]['province_id'] = $source['province']['id'];
                        $countryArea['province']       = $source['province']['id'] . ',' . $source['province']['name'];
                    }
                    if (!Arr::hasArr($source, 'province')) {
                        $response[$key]['province']    = 0;
                        $response[$key]['province_en'] = 0;
                        $response[$key]['province_id'] = 0;
                        $countryArea['province']       = '0,0';
                    }

                    if (Arr::hasArr($source, 'city')) {
                        $response[$key]['city']    = $source['city']['name'];
                        $response[$key]['city_en'] = $source['city']['name_en'];
                        $response[$key]['city_id'] = $source['city']['id'];
                        $countryArea['city']       = $source['city']['id'] . ',' . $source['city']['name'];
                    }
                    if (!Arr::hasArr($source, 'city')) {
                        $response[$key]['city']    = 0;
                        $response[$key]['city_en'] = 0;
                        $response[$key]['city_id'] = 0;
                    }
                    if (Arr::hasArr($source, 'area')) {
                        $response[$key]['district']    = $source['area']['name'];
                        $response[$key]['district_en'] = $source['area']['name_en'];
                        $response[$key]['district_id'] = $source['area']['id'];
                        $countryArea['district']       = $source['area']['id'] . ',' . $source['area']['name'];
                    }
                    if (!Arr::hasArr($source, 'area')) {
                        $response[$key]['district']    = 0;
                        $response[$key]['district_en'] = 0;
                        $response[$key]['district_id'] = 0;

                    }


                    if (Arr::hasArr($source, 'street')) {
                        $response[$key]['street']    = $source['street']['name'];
                        $response[$key]['street_en'] = $source['street']['name_en'];
                        $response[$key]['street_id'] = $source['street']['id'];
                        $countryArea['street']       = $source['street']['id'] . ',' . $source['street']['name'];
                    }

                    if (!Arr::hasArr($source, 'street')) {
                        $response[$key]['street']    = 0;
                        $response[$key]['street_en'] = 0;
                        $response[$key]['street_id'] = 0;
                    }
                    unset($response[$key]['id']);
                    unset($response[$key]['q']);
                    unset($response[$key]['source']);
                    $detail = $val['source']['detail'];
                }

                $response[$key]['detail']      = $detail;
                $response[$key]['countryArea'] = $countryArea;
                unset($idArr, $detail, $nameArr, $nameEnArr);
            }
        }

        return $response;
    }


    /**
     * @DOC   : 订单整理
     * @param array $orderSingle
     * @param array $sender //寄件人信息
     * @param string $order_sys_sn //系统订单号
     * @param string $batch_sn //批次号
     * @return array
     * @throws Exception
     * @Author: wangfei
     * @date  : 2023-03-15 2023
     */
    protected function handle(array $orderSingle, array $sender, string $order_sys_sn, string $batch_sn)
    {
        $order = $orderSingle;

        unset($order['receiver'], $order['item'], $order['sender']);
        $member_uid       = $orderSingle['member_uid'];
        $parent_join_uid  = $orderSingle['parent_join_uid'];
        $parent_agent_uid = $orderSingle['parent_agent_uid'];
        $item             = $orderSingle['item'];
        $order_weight     = 0;

        foreach ($item as $key => $val) {
            // 删掉item的重量
            if ($order_weight == 0) {
                $order_weight = $val['order_weight'] ?? 0;
            }
            unset($item[$key]['order_weight']);
            $item[$key]['order_sys_sn']     = $order_sys_sn;
            $item[$key]['member_uid']       = $member_uid;
            $item[$key]['parent_join_uid']  = $parent_join_uid;
            $item[$key]['parent_agent_uid'] = $parent_agent_uid;
            $item[$key]['add_time']         = $orderSingle['add_time'];
            $item_nun                       = empty($val['item_num']) ? 1 : $val['item_num'];
            $item[$key]['item_total']       = $item_nun * $val['item_price'];
        }
        // 判断当前商品重量是否存在
        if ($order_weight == 0) {
            throw new HomeException('来源单号：' . $order['from_order_sn'] . '包裹重量必填');
        }
        // 重量添加
        $order['order_weight'] = $order_weight;
        //收件人地址
        $receiver = $orderSingle['receiver'];

        // 修改收件人国家ID
        $receiver['country_id']   = CountryAreaModel::where('id', $receiver['country_id'])->value('country_id');
        $receiver['city']         = Arr::hasArr($receiver, 'city') ? $receiver['city'] : "";
        $receiver['city_id']      = Arr::hasArr($receiver, 'city_id') ? $receiver['city_id'] : 0;
        $receiver['district']     = Arr::hasArr($receiver, 'district') ? $receiver['district'] : "";
        $receiver['district_id']  = Arr::hasArr($receiver, 'district_id') ? $receiver['district_id'] : 0;
        $receiver['street']       = Arr::hasArr($receiver, 'street') ? $receiver['street'] : "";
        $receiver['street_id']    = Arr::hasArr($receiver, 'street_id') ? $receiver['street_id'] : 0;
        $receiver['area_code']    = Arr::hasArr($receiver, 'area_code') ? $receiver['area_code'] : 0;
        $receiver['name']         = (Arr::hasArr($receiver, 'name')) ? base64_encode((new Crypt)->encrypt($receiver['name'])) : "";
        $receiver['phone']        = (Arr::hasArr($receiver, 'phone')) ? base64_encode((new Crypt)->encrypt($receiver['phone'])) : "";
        $receiver['mobile']       = (Arr::hasArr($receiver, 'mobile')) ? base64_encode((new Crypt)->encrypt($receiver['mobile'])) : "";
        $receiver['order_sys_sn'] = $order_sys_sn;
        $receiver['member_uid']   = $member_uid;

        unset($receiver['md5']);
        //寄件人：
        if (isset($orderSingle['sender']) && !empty($orderSingle['sender'])) {
            $orderSenderSingle                     = $orderSingle['sender'];
            $orderSenderSingle['member_uid']       = $member_uid;
            $orderSenderSingle['parent_join_uid']  = $parent_join_uid;
            $orderSenderSingle['parent_agent_uid'] = $parent_agent_uid;
            $orderSenderSingle['batch_sn']         = $batch_sn;
            $orderSenderSingle['order_sys_sn']     = $order_sys_sn;
            $orderSenderSingle                     = $this->handleSenderAddress($orderSenderSingle);

            if ($sender['md5'] != $orderSenderSingle['md5']) {
                $singleSender           = $orderSenderSingle['sender'];
                $singleSender['name']   = (Arr::hasArr($singleSender, 'name')) ? base64_encode((new Crypt)->encrypt($singleSender['name'])) : "";
                $singleSender['phone']  = (Arr::hasArr($singleSender, 'phone')) ? base64_encode((new Crypt)->encrypt($singleSender['phone'])) : "";
                $singleSender['mobile'] = (Arr::hasArr($singleSender, 'mobile')) ? base64_encode((new Crypt)->encrypt($singleSender['mobile'])) : "";
                unset($singleSender['md5']);
                $result['sender'] = $singleSender;
                unset($singleSender, $orderSenderSingle);
            }
        }

        $result['order']    = $order;
        $result['item']     = $item;
        $result['receiver'] = $receiver;
        return $result;
    }

}
