<?php

namespace App\JsonRpc;
/**
 * 仓库接口
 */
interface WareServiceInterface
{
    /**
     * @DOC   : 查询包裹
     * @Name  : parcelQuery
     * @Author: wangfei
     * @date  : 2025-02 15:43
     * @param string $ak
     * @param string $parcel_sn
     * @param string $location 货位号
     * @return mixed
     *
     */
    function parcelQuery(string $ak, string $parcel_sn, string $location = '');

    /**
     * @DOC   :
     * @Name  : parcelUpBatch
     * @Author: wangfei
     * @date  : 2025-02 17:45
     * @param string $ak 仓库AK
     * @param string $location 货架
     * @param array $data 绑定的包裹
     * @return mixed
     *
     */
    public function parcelUpBatch(string $ak, string $location, array $data);

    /**
     * @DOC   : 到货扫描
     * @Name  : parcelInWareScan
     * @Author: wangfei
     * @date  : 2025-02 17:13
     * @param string $ak
     * @param string $location
     * @param array $data
     * @return mixed
     *
     */
    public function parcelInWareScan(string $ak, string $location, array $data);


}
