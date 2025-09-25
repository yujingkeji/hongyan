<?php

namespace App\JsonRpc\Service;

use App\JsonRpc\WareServiceInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\RpcServer\Annotation\RpcService;
use App\Service\WareOperateService;

#[RpcService(name: "WareService", protocol: "jsonrpc-http", server: "jsonrpc-http", publishTo: "nacos")]
class WareService implements WareServiceInterface
{

    #[Inject]
    protected WareOperateService $wareOperateService;

    public function __construct()
    {

    }

    #商家之前查询

    /**
     * @DOC   :
     * @Name  : queryParcel
     * @Author: wangfei
     * @date  : 2025-02 15:02
     * @param string $ak 密钥
     * @param string $parcel_sn 订单号、运单号、送仓单号。扫描的啥就是啥
     * @param string $location ,
     * @param int $parcel_code =1 包裹编码：默认为1、当同仓库、同用户出现两个、或多个相同单号的包裹时、编码自动+1、且必须强制备注。
     * @return array|void
     *
     */
    public function parcelQuery(string $ak, string $parcel_sn, string $location = '')
    {
        return $this->wareOperateService->parcelQuery($ak, $parcel_sn, $location);
    }


    /**
     * @DOC   : 批量上架货架
     * @DOC   :
     * @DOC   :
     * @Name  : parcelUpBatch
     * @Author: wangfei
     * @date  : 2025-02 18:11
     * @param string $ak
     * @param string $location
     * @param array $data
     * @return array
     *
     */
    public function parcelUpBatch(string $ak, string $location, array $data)
    {
        return $this->wareOperateService->parcelUpBatch($ak, $location, $data);
    }

    /**
     * @DOC   : 到仓扫描
     * @Name  : parcelInWareScan
     * @Author: wangfei
     * @date  : 2025-02 17:13
     * @param string $ak
     * @param string $location
     * @param array $data
     * @return array|null
     *
     */
    public function parcelInWareScan(string $ak, string $location, array $data)
    {
        return $this->wareOperateService->parcelInWareScan($ak, $location, $data);
    }


}
