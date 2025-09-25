<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Admin;

use App\Common\Lib\Arr;
use App\Common\Lib\Crypt;
use App\Common\Lib\Str;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Controller\AbstractController;

use App\Model\ParcelModel;
use App\Service\Cache\BaseCacheService;
use App\Service\Express\ExpressService;
use App\Service\OrderToParcelService;
use App\Service\QueueService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Snowflake\IdGeneratorInterface;
use Psr\Container\ContainerInterface;
use function App\Common\Send;

#[Controller(prefix: '/', server: 'httpAdmin')]
class IndexController extends AbstractController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;

    #[Inject]
    protected QueueService $queueService;

    protected int $channel_id = 100;

    #[Inject]
    protected OrderToParcelService $OrderToParcelService;

    #[Inject]
    protected Crypt $Crypt;

    /*#[Inject]
    protected ExpressService $ExpressService;*/

    #[RequestMapping(path: 'index/test', methods: 'get,post')]
    public function test()
    {

        $filed = ['order_sys_sn', 'transport_sn', 'member_uid', 'parent_join_uid', 'parent_agent_uid', 'batch_sn', 'line_id', 'ware_id', 'parcel_status',
                  'parcel_weight', 'product_id', 'channel_id', 'add_time'];

        $painter = ParcelModel::with(['order' => function ($query) {
            $query->with(['sender' => function ($query) {
                $query->select();
            }, 'receiver'])->select();
        }])->select($filed)->paginate(10);

        //  $painter->toArray();

        // $parcelData = $painter->items();
        //var_dump($painter['data']);
        /* $generator = \Hyperf\Support\make(IdGeneratorInterface::class);
         $k         = $generator->generate();*/
        /*
                $generator = \Hyperf\Support\make(UserDefinedIdGenerator::class);
                $uid       = 20190620;
                $ids       = [];
                //  for ($i = 0; $i <= 10000; $i++) {
                $id = $generator->generate($uid);
                array_push($ids, $id);
                $ks = $generator->degenerate($id);
                //}


                $data = [
                    'id' => $ids,
                    'ks' =>$ks
                ];*/

        return $this->response->json($painter);

        $where['order_sys_sn'] = 16865514820001;
        $ExpressService        = \Hyperf\Support\make(ExpressService::class, ['TRANSPORT']);
        $data                  = $ExpressService->getOrderData(16865514820001);
        $data['sender']        = $this->handleDecrypt($data['sender']);
        $data['receiver']      = $this->handleDecrypt($data['receiver']);
        // return $this->response->raw(json_encode($data));
        $channel_id         = 16;
        $content['data']    = $data;
        $channel            = $this->OrderToParcelService->ParcelSend($channel_id);
        $content['channel'] = $channel;
        return $this->response->raw(json_encode($content));
        return $data;
    }

    public function handleDecrypt(array $Address, bool $Star = false)
    {

        $name = '';
        if (Arr::hasArr($Address, 'name')) {
            $name = base64_decode($Address["name"]);
            $name = $this->Crypt->decrypt($name);
            $name = ($Star) ? Str::centerStar($name) : $name;
        }
        $Address['name'] = $name;
        $phone           = '';
        if (Arr::hasArr($Address, 'phone')) {
            $phone = base64_decode($Address["phone"], true);
            $phone = $this->Crypt->decrypt($phone);
            $phone = ($Star) ? Str::centerStar($phone) : $phone;
        }
        $Address['phone'] = $phone;
        $mobile           = '';
        if (Arr::hasArr($Address, 'mobile')) {
            $mobile = base64_decode($Address["mobile"]);
            $mobile = $this->Crypt->decrypt($mobile);
            $mobile = ($Star) ? Str::centerStar($mobile) : $mobile;
        }
        $Address['mobile'] = $mobile;
        return $Address;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[RequestMapping(path: '', methods: 'get,post')]
    public function index(RequestInterface $request, ContainerInterface $container)
    {
        $UserDefinedIdGenerator = \Hyperf\Support\make(UserDefinedIdGenerator::class);
        $data                   = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        //  foreach ($data as $k => $v) {
        for ($i = 0; $i <= 100; $i++) {
            $v = $UserDefinedIdGenerator->generate(16);
            echo $v . PHP_EOL;
        }

        echo '+++++++++++++++++++++++++++++++++++++' . PHP_EOL;
    }

    #[RequestMapping(path: 'cainiao', methods: 'get,post')]
    public function cainiao(RequestInterface $request, ContainerInterface $container)
    {
        $data = [
            'tracesList' => [
                'logisticProviderID' => "sydgj",
                'mailNos'            => "SC820000000001",
                'txLogisticID'       => "",
                "traces"             => [
                    [
                        'time'         => "2023-08-25 10:05:39",
                        'desc'         => "111",
                        'facilityType' => "1", //站点类型：1:网点 2:中转中心/分拨中心
                        'facilityNo'   => "8000001", //站点类型：1:网点 2:中转中心/分拨中心
                        'facilityName' => "111",
                        'nextMailNo'   => "",
                        'nextTpCode'   => "YUNDA",
                        'country'      => "Korea",
                        'tz'           => "+8",
                        'action'       => "OM_CONSIGN",
                        'outBizCode'   => 1692929139,
                    ]
                ],
            ]
        ];


        $linkUrl = 'http://link.cainiao.com/gateway/link.do';
//        $content = ["testkey" => "testkey", "testdata" => "testdata"];     // 如果接口配置为json格式，这儿内容就是json，否则是xml格式
        $appSecret = '18o2pP069X91z61fLu0C8857n2uJ43r1';    // APPKEY对应的秘钥
        $cpCode    = 'DISTRIBUTOR_31204423';     //调用方的CPCODE
        $msgType   = 'TRACEPUSH';    //调用的API名
        $toCode    = '';        //调用的目标TOCODE，有些接口TOCODE可以不用填写

        $content = ["appkey" => "812527", "data" => $data];

        $digest = base64_encode(md5(json_encode($content) . $appSecret));     //生成签名


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $linkUrl);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/x-www-form-urlencoded']);
        $post_data = 'msg_type=' . $msgType
            . '&to_code=' . $toCode
            . '&logistics_interface=' . json_encode($content)
            . '&data_digest=' . urlencode($digest)
            . '&logistic_provider_id=' . urlencode($cpCode);

        echo "Post body is: \n" . json_encode($post_data) . "\n";
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_POST, 1);

        echo "Start to run...\n";
        $output = curl_exec($ch);
        curl_close($ch);


        return $output;
    }
}
