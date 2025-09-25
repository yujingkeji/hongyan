<?php
/**
 * Created by PhpStorm.
 * User yfd
 * Date 2024/3/6
 */

namespace App\JsonRpc;


interface RecordServiceInterface
{
    public function getRecord(array $params);

    public function addRecord(int $appKey, array $params);

    /**
     * @DOC skuId查询
     * @Name   batchBySkuId
     * @Author wangfei
     * @date   2024/3/8 2024
     * @param array $ids
     * @return mixed
     */
    public function batchBySkuId(array $ids);

    /**
     * @DOC 远程获取数据计算税金匹配CC，BC备案信息
     * @Name   batchTaxCcBySkuId
     * @Author wangfei
     * @date   2024/3/30 2024
     * @param array $ids
     * @return mixed
     */
    public function batchTaxCcBySkuId(array $ids);

    /**
     * @DOC baseId查询
     * @Name   batchByBaseId
     * @Author wangfei
     * @date   2024/3/8 2024
     * @param array $ids
     * @return mixed
     */
    public function batchByBaseId(array $ids);


    public function getCategoryItemList(array $itemList);

    public function getGoodsItemByName(array $item);

    public function batchGoodsItemByName(array $itemList);
}