<?php

namespace App\JsonRpc;


interface BaseServiceInterface
{
    /**
     * @DOC   :
     * @Name  : countryAreaByLabel
     * @Author: wangfei
     * @date  : 2025-04 16:53
     * @date  : 2025-04 16:46
     * @param array $params {"label_id":3,"level":3}  levle默认为 3
     * @return mixed
     *
     */
    public function countryAreaByLabel(array $params);

    /**
     * @DOC   : 根据国家id获取国家地区标签
     * @Name  : countryAreaLabel
     * @Author: wangfei
     * @date  : 2025-04 16:25
     * @param int $country_id
     * @return mixed
     */
    public function countryAreaLabel(int $country_id);

    public function categoryAnalyse(array $params);

    /**
     * @DOC   :根据商品名称 分析商品类别
     * @Name  : categoryBatchAnalyse
     * @Author: wangfei
     * @date  : 2025-04 13:42
     * @param array $params [{"text":"JM洗面奶","f":2},{"text":"JM洗面奶"},{"text":"奶粉"}]
     * @return array|false|mixed
     *
     */
    public function categoryBatchAnalyse(array $params);

    public function captcha();

    public function captchaCheck(array $params);

    public function avatar();

    public function countryCode(string $keyword = null, int $page = 1, int $limit = 10);

    public function brand(string $keyword = null, int $page = 1, int $limit = 200);

    public function brandById(array $ids);

    public function unit(string $keyword = null, int $page = 1, int $limit = 200);

    public function countryArea(string $keyword = null, int $parent_id = 0, int $country_id = 0);

    public function countryAreaByPage(int $page = 1, int $limit = 200);

    public function recordGoodsCategoryByPage(int $page = 1, int $limit = 200);

    public function recordGoodsCategory(string $keyword = null, int $parent_id = 0, int $country_id = 0);

    public function recordGoodsCategoryByCountry($country_id = 1);

    public function recordGoodsCategoryByTax(array $tax_code);

    public function countryAreaInfo(array $ids);

    public function recordGoodsCategoryInfo(array $ids);

    public function elasticRecordGoodsWordSearch(array $item);

    public function port(string $keyword = null, int $port_id = 0, int $page = 1, int $limit = 200);


    /**
     * @Doc 分词
     * @Doc https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-analyzers.html
     * @Author wangfei
     * @Date 2024/8/9 下午12:39
     * @param string $text
     * @param string $analyzer
     * @return mixed
     */
    public function analyse(string $text, string $analyzer = 'ik_smart');

    /**
     * @Doc 根据分词查询分类
     * @Doc https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-analyzers.html
     * @Author wangfei
     * @Date 2024/8/9 下午1:32
     * @param string $text
     * @param string $analyzer
     * @return mixed
     */
    public function recordGoodsCategoryParticiple(string $text, string $analyzer = 'ik_smart', int $parent_id = 0);


}
