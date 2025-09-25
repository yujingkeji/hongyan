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

namespace App\Controller\Home\Parcels;

use App\Common\Lib\Arr;
use App\Common\Lib\Str;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Controller\Home\AbstractController;
use App\Controller\Home\Orders\OrderBaseController;
use App\Exception\HomeException;
use App\Model\BlModel;
use App\Model\BlNodeModel;
use App\Model\ParcelModel;
use App\Model\ParcelSendModel;
use App\Model\PriceTemplateModel;
use App\Request\BlRequest;
use App\Service\BlService;
use App\Request\OrdersRequest;
use App\Service\Cache\BaseCacheService;
use App\Service\Express\ExpressService;
use App\Service\ParcelService;
use App\Service\QueueService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Rule;
use PharIo\Version\Exception;
use phpseclib3\Math\BigInteger\Engines\PHP;

#[Controller(prefix: 'parcels/bl')]
class BlController extends ParcelBaseController
{
    #[Inject]
    protected BaseCacheService          $baseCacheService;
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;


    #[RequestMapping(path: 'search', methods: 'get,post')]
    public function search(RequestInterface $request)
    {
        $param = $this->request->all();
        switch ($this->request->UserInfo['role_id']) {
            default:
                throw new HomeException('禁止非平台代理访问', 201);
                break;
            case 1:
            case 2:
                break;
        }
        $bl_main_sn = '';
        if (Arr::hasArr($param, 'bl_main_sn')) {
            $bl_main_sn = $param['bl_main_sn'];
        }
        $result['code'] = 201;
        $result['msg']  = '查询失败';
        $blSerice       = \Hyperf\Support\make(BlService::class);
        $data           = $blSerice->BlSearch($bl_main_sn);
        $result['code'] = 200;
        $result['msg']  = '加载完成';
        $result['data'] = $data;
        return $this->response->json($result);
    }


}
