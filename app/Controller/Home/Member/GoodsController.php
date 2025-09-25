<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Common\Lib\Str;
use App\Common\Lib\Unique;
use App\Common\Lib\UserDefinedIdGenerator;
use App\Constants\Logger;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\BrandModel;
use App\Model\GoodsBaseModel;
use App\Model\GoodsCategoryItemModel;
use App\Model\GoodsSkuModel;
use App\Model\GoodsTemplateModel;
use App\Model\TemplateCategoryModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\CategoryTemplateService;
use App\Service\GoodsRecordService;
use App\Service\GoodsService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;
use function App\Common\batchUpdateSql;
use function App\Common\hasSort;


#[Controller(prefix: "member/goods")]
class GoodsController extends HomeBaseController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;

    /**
     * @DOC 商品列表
     */
    #[RequestMapping(path: 'lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $param    = $request->all();
        $useWhere = $this->useWhere();
        $data     = \Hyperf\Support\make(GoodsService::class)->GoodsLists($param, $useWhere);
        return $this->response->json($data);
    }

    /*************************************************************************************/
    /**
     * @DOC 商品详情
     */
    #[RequestMapping(path: 'info', methods: 'post')]
    public function info(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'goods_base_id' => ['required'],
        ], [
            'goods_base_id.required' => '商品ID错误',
            'goods_base_id.integer'  => '商品ID错误',
        ]);

        $useWhere = $this->useWhere();

        $goods = GoodsBaseModel::where($useWhere['where'])
            ->where('goods_base_id', $param['goods_base_id'])
            ->with(['sku', 'cc', 'bc'])
            ->first();
        if ($goods) {
            $goods = $goods->toArray();
            if (Arr::hasArr($goods, 'category_item_id')) {
                $recordCategoryGoodsCache = $this->baseCacheService->recordCategoryGoodsCache();
                $handleGoodsCategory      = Arr::handleGoodsCategory($recordCategoryGoodsCache, $goods['category_item_id']);
                unset($handleGoodsCategory['data']);
                $goods['category'] = $handleGoodsCategory;
            }
            return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $goods]);
        }
        return $this->response->json(['code' => 201, 'msg' => '获取失败', 'data' => []]);
    }

    /**
     * @DOC   : 新增、审核的验证规则
     * @Name  : validationRule
     * @Author: wangfei
     * @date  : 2025-03 15:27
     * @return array
     *
     */
    protected function validationRule()
    {

        $rule['rule']     =
            [
                'goods_base_id'       => ['integer'],
                'category_item_id'    => ['required', 'integer'],
                'goods_code'          => ['required', 'string', 'max:255', 'nullable'],
                'goods_name'          => ['required', 'string', 'max:255'],
                'goods_name_source'   => ['string', 'max:255', 'nullable'],
                'goods_name_en'       => ['string', 'max:255', 'nullable'],
                'short_name'          => ['required', 'string', 'max:255'],
                'brand_id'            => ['integer'],
                'brand_name'          => ['string', 'max:100'],
                'brand_en'            => ['string', 'max:100'],
                'origin_country'      => ['integer'],//原产地（国家地区）
                'send_country'        => ['integer'], //发出港口或者国家
                'buy_link'            => ['string', 'url', 'nullable'],
                'record_status'       => ['integer', Rule::in([0, 1, 2, 3, 4])],//'来源数据的\r\n备案状态\r\n0:待维护\r\n1:已维护\r\n2:审核中\r\n3:已通过\r\n4:已拒绝',
                'record_info'         => ['string', 'nullable'], //审核备注
                'goods_info'          => ['string', 'nullable'], //商品备注
                'cc_checked'          => ['in:0,1', 'nullable'], //商品备注
                'bc_checked'          => ['in:0,1', 'nullable'], //商品备注
                'goods_md5'           => ['string', 'between:32,32', 'nullable'], //商品备注
                'sku.*.sku_id'        => ['nullable', 'integer'],
                'sku.*.goods_base_id' => ['string'],
                'sku.*.sku_code'      => ['required', 'string'],
                'sku.*.price'         => ['numeric'],
                'sku.*.price_unit'    => ['string'],
                'sku.*.in_number'     => ['required', 'numeric'],//内置数量
                'sku.*.spec'          => ['required', 'string'],//规格、含量、容量等
                'sku.*.spec_unit'     => ['nullable', 'string'],//规格、含量、容量单位
                'sku.*.component'     => ['string', 'nullable'],//成分
                'sku.*.model_number'  => ['string', 'nullable'], //型号
                'sku.*.barcode'       => ['string'], //条码
                'sku.*.gross_weight'  => ['numeric'], //毛重
                'sku.*.suttle_weigh'  => ['numeric'], //净重
                'cc.tax_number'       => ['required_if:cc_checked,1', 'string'],//行邮税号
                'cc.tax_rate'         => ['required_if:cc_checked,1', 'integer'],//行邮税率
                'cc.dutiable_value'   => ['numeric'],//完税价格
                'bc.hs_code'          => ['required_if:bc_checked,1', 'string'],//海关编码
                'bc.tax_unit'         => ['required_if:bc_checked,1', 'string'],//完税单位
                'bc.tax_rate'         => ['required_if:bc_checked,1', 'numeric'],//商品税率(BC)
                'bc.vat_rate'         => ['numeric'],  //商品税率(BC)
                'bc.suit_country'     => ['integer'],   //适用标准（国别）：当前商品是否适用当前国家的标准。
                'bc.first_unit'       => ['string', 'nullable'],   //第一法定单位
                'bc.second_unit'      => ['string', 'nullable'],   //第二法定单位
                'bc.first_quantity'   => ['numeric'],  //第一法定数量
                'bc.second_quantity'  => ['numeric'],  //第二法定数量
                'bc.supplier'         => ['string', 'nullable'],   //供应商
                'bc.component'        => ['string', 'nullable'],   //商品成分
                'bc.desc'             => ['string', 'nullable'],   //商品描叙
                'bc.goods_function'   => ['string', 'nullable'],   //商品功能
                'bc.goods_purpose'    => ['string', 'nullable'],   //商品用途
            ];
        $rule['messages'] =
            [
                'goods_base_id.required'      => '商品ID不能为空',
                'goods_base_id.integer'       => '商品ID格式不正确',
                'category_item_id.required'   => '商品分类不能为空',
                'category_item_id.integer'    => '商品分类格式不正确',
                'goods_code.string'           => '商品编码格式不正确',
                'goods_code.max'              => '商品编码长度不能超过255个字符',
                'goods_name.required'         => '商品名称不能为空',
                'goods_name.string'           => '商品名称格式不正确',
                'goods_name.max'              => '商品名称长度不能超过255个字符',
                'goods_name_source.string'    => '商品名称(原)格式不正确',
                'goods_name_en.string'        => '商品名称(英文)格式不正确',
                'short_name.required'         => '商品简称不能为空',
                'short_name.string'           => '商品简称格式不正确',
                'brand_id.integer'            => '品牌ID格式不正确',
                'brand_name.string'           => '品牌名称格式不正确',
                'brand_en.string'             => '品牌名称(英文)格式不正确',
                'origin_country.integer'      => '原产地（国家地区）格式不正确、应为国家地区ID',
                'send_country.integer'        => '发出港口或者国家格式不正确、应为国家地区ID',
                'buy_link.url'                => '购买链接格式不正确',
                'record_status.required'      => '备案状态不能为空',
                'record_status.integer'       => '备案状态格式不正确',
                'record_info.string'          => '审核备注格式不正确',
                'goods_info.string'           => '商品备注格式不正确',
                'sku.*.sku_code.required'     => 'SKU编码不能为空',
                'sku.*.sku_code.string'       => 'SKU编码格式不正确',
                'sku.*.price.required'        => '价格不能为空',
                'sku.*.price.numeric'         => '价格格式不正确',
                'sku.*.price_unit.required'   => '价格单位不能为空',
                'sku.*.price_unit.string'     => '价格单位格式不正确',
                'sku.*.in_number.required'    => '内置数量不能为空',
                'sku.*.in_number.numeric'     => '内置数量格式不正确',
                'sku.*.spec.required'         => '规格不能为空',
                'sku.*.spec.string'           => '规格格式不正确',
                'sku.*.spec_unit.required'    => '规格单位不能为空',
                'sku.*.spec_unit.string'      => '规格单位格式不正确',
                'sku.*.component.string'      => '成分格式不正确',
                'sku.*.model_number.string'   => '型号格式不正确',
                'sku.*.barcode.required'      => '条码不能为空',
                'sku.*.barcode.string'        => '条码格式不正确',
                'sku.*.gross_weight.numeric'  => '毛重格式不正确',
                'sku.*.suttle_weigh.required' => '净重不能为空',
                'sku.*.suttle_weigh.numeric'  => '净重格式不正确',
                'cc.tax_number.required_if'   => '行邮税号不能为空',
                'cc.tax_rate.required_if'     => '行邮税率不能为空',
                'cc.dutiable_value.numeric'   => '完税价格格式不正确',
                'bc.hs_code.required_if'      => 'bc 海关编码不能为空',
                'bc.tax_unit.required_if'     => 'bc 完税单位不能为空',
                'bc.tax_rate.required_if'     => 'bc 商品税率不能为空',
                'bc.vat_rate.numeric'         => 'bc 增值税税率格式不正确',
                'bc.suit_country.integer'     => 'bc 适用标准（国别）格式不正确',
                'bc.first_unit.string'        => 'bc 第一法定单位格式不正确',
                'bc.second_unit.string'       => 'bc 第二法定单位格式不正确',
                'bc.first_quantity.numeric'   => 'bc 第一法定数量格式不正确',
                'bc.second_quantity.numeric'  => 'bc 第二法定数量格式不正确',
                'bc.supplier.string'          => 'bc 供应商格式不正确',
                'bc.component.string'         => 'bc 商品成分格式不正确',
                'bc.desc.string'              => 'bc 商品描叙格式不正确',
                'bc.goods_function.string'    => 'bc 商品功能格式为字符串',
                'bc.goods_purpose.string'     => 'bc 商品用途格式不正确',
            ];


        return $rule;
    }

    /**
     * @DOC 商品新增
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $member = $request->UserInfo;
        if (!Arr::hasArr($params, ['category_item_id'])) {
            throw new HomeException('请选择商品分类');
        }
        $CategoryTemplate   = make(CategoryTemplateService::class)->template(category_id: $params['category_item_id']);
        $TemplateValidation = $CategoryTemplate['validation'];
        $validationRule     = $this->validationRule();
        $rule               = array_merge_recursive($validationRule['rule'], $TemplateValidation['rule']);
        $messages           = array_merge_recursive($validationRule['messages'], $TemplateValidation['messages']);
        $params             = make(LibValidation::class)->validate($params, $rule, $messages);

        //判断商品编码是否存在
        $baseDbResult  = [];
        $goods_base_id = $params['goods_base_id'] ?? '';
        $baseDbResult  = $this->checkBaseCodeUnique(member: $member, goods_base_id: $goods_base_id, goodsCode: $params['goods_code']);
        $member_uid    = $baseDbResult['member_uid'] ?? $member['uid'];  //当商品编码不存在时，使用当前登录用户ID
        $SkuCode       = array_column($params['sku'], 'sku_code');
        $SkuCode       = Arr::delEmpty($SkuCode);
        $this->checkSkuCodeUnique(source_member_uid: $member_uid, goods_base_id: $goods_base_id, skuCode: $SkuCode);
        $barCode = array_column($params['sku'], 'barcode');
        $barCode = Arr::delEmpty($barCode);
        $this->checkBarCodeUnique(source_member_uid: $member_uid, goods_base_id: $goods_base_id, barCode: $barCode);
        $goods_base_id = make(UserDefinedIdGenerator::class)->generate($member['uid']);
        $handleData    = $this->handleData(member: $member, params: $params, goodsBaseDb: $baseDbResult, goods_base_id: $goods_base_id);
        Db::beginTransaction();
        try {
            if (empty($baseDbResult)) {
                Db::table('goods_base')->insert($handleData['base']);
                if ($params['cc_checked'] == 1 && !empty($params['cc'])) {
                    Db::table('goods_cc')->insert($handleData['cc']);
                }
                if ($params['bc_checked'] == 1 && !empty($params['bc'])) {
                    Db::table('goods_bc')->insert($handleData['bc']);
                }
                Db::table('goods_sku')->insert($handleData['sku']['insert']);
            }
            if (!empty($baseDbResult)) {
                Db::table('goods_base')->updateOrInsert(['goods_base_id' => $baseDbResult['goods_base_id']], $handleData['base']);
                if (!empty($handleData['cc'])) {
                    Db::table('goods_cc')->updateOrInsert(['goods_base_id' => $baseDbResult['goods_base_id']], $handleData['cc']);
                }
                if (!empty($handleData['bc'])) {
                    Db::table('goods_bc')->updateOrInsert(['goods_base_id' => $baseDbResult['goods_base_id']], $handleData['bc']);
                }
                //添加Sku
                if (Arr::hasArr($handleData['sku'], 'insert')) {
                    $insert                  = $handleData['sku']['insert'];
                    $addArr['goods_base_id'] = $baseDbResult['goods_base_id'];
                    $insert                  = Arr::pushArr($addArr, $insert);
                    Db::table('goods_sku')->insert($insert);
                }
                //批量更新Sku
                if (Arr::hasArr($handleData['sku'], 'update')) {
                    $updateBrandDataSql = batchUpdateSql('goods_sku', $handleData['sku']['update'], ['sku_id']);
                    Db::update($updateBrandDataSql);
                }
                //删除Sku
                if (Arr::hasArr($handleData['sku'], 'del')) {
                    Db::table('goods_sku')->whereIn('sku_id', $handleData['sku']['del'])->delete();
                }
            }
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '操作成功', 'order_sys_sn' => $goods_base_id]);
        } catch (\Exception $e) {
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => '添加版本失败：' . $e->getMessage(), 'data' => []]);
        }

    }


    /**
     * @DOC 批量新增
     */
    #[RequestMapping(path: 'batch', methods: 'post')]
    public function batch(RequestInterface $request): ResponseInterface
    {
        $params                  = $request->all();
        $validationRule          = $this->validationRule();
        $LibValidation           = make(LibValidation::class);
        $CategoryTemplateService = make(CategoryTemplateService::class);
        // 校验参数
        $resultParams = [];
        foreach ($params as $key => $param) {
            try {
                if (isset($param['category_item_id']) && $param['category_item_id'] > 0) {
                    $CategoryTemplate   = $CategoryTemplateService->template(category_id: $param['category_item_id']);
                    $TemplateValidation = $CategoryTemplate['validation'];
                    $rule               = array_merge_recursive($validationRule['rule'], $TemplateValidation['rule']);
                    $messages           = array_merge_recursive($validationRule['messages'], $TemplateValidation['messages']);
                    $resultParams[]     = $LibValidation->validate($param, $rule, $messages);
                } else {
                    $resultParams[] = $LibValidation->validate($param, $validationRule['rule'], $validationRule['messages']);
                }
            } catch (HomeException $e) {
                throw new HomeException('第 ' . ($key + 1) . ' 行数据：' . $e->getMessage());
            }
        }
        $this->checkImportData($resultParams); //检测输入数据在数据库是否存在
        $handle = $this->batchHandle($resultParams); // 批量数据整理
        /* Db::beginTransaction();
         try {
             Db::table('goods_base')->insert($handle['base']);
             Db::table('goods_sku')->insert($handle['sku']);
             Db::commit();
             return $this->response->json(['code' => 200, 'msg' => '批量添加成功', 'data' => []]);
         } catch (\Exception $e) {
             Db::rollback();
             return $this->response->json(['code' => 201, 'msg' => '批量添加失败：' . $e->getMessage(), 'data' => []]);
         }*/
    }

    /**
     * @DOC 检测输入数据
     */
    protected function checkImportData($param)
    {
        $goodsCode = array_column($param, 'goods_code');
        $skuArr    = array_merge(...array_column($param, 'sku'));
        $skuCode   = array_column($skuArr, 'sku_code');
        $barCode   = array_column($skuArr, 'barcode');
        $barCode   = Arr::delEmpty($barCode);
        $skuCode   = Arr::delEmpty($skuCode);
        //检测重复
        $goodsCodeRepeat = Arr::fetchRepeatInArray($goodsCode);
        $barCodeRepeat   = Arr::fetchRepeatInArray($barCode);
        $skuCodeRepeat   = Arr::fetchRepeatInArray($skuCode);
        if (!empty($goodsCodeRepeat)) {
            $goodsCodeRepeat = array_unique($goodsCodeRepeat);
            throw new HomeException('商品货号： ' . implode(',', $goodsCodeRepeat) . " 重复，请检查", 201);
        }
        if (!empty($skuCodeRepeat)) {
            $skuCodeRepeat = array_unique($skuCodeRepeat);
            throw new HomeException('商品编码： ' . implode(',', $skuCodeRepeat) . " 重复，请检查", 201);
        }
        if (!empty($barCodeRepeat)) {
            $barCodeRepeat = array_unique($barCodeRepeat);
            throw new HomeException('商品条码： ' . implode(',', $barCodeRepeat) . " 重复，请检查", 201);
        }

        //检查是否存在
        $this->checkGoodsCode($goodsCode);
        $this->checkSkuBarcode($skuCode, $barCode);
    }

    /**
     * @DOC 整理输入数据为可以保存的数据
     */
    protected function batchHandle($param)
    {
        $OnlyNumber = new Unique();
        $OnlyNumber->uniqueTime();
        $useWhere = $this->useWhere();
        $baseArr  = [];
        $skuArr   = [];
        foreach ($param as $key => $val) {
            $base = $val;
            unset($base['sku']);
            $userBase['goods_base_id'] = $OnlyNumber->unique();;
            $userBase  = array_merge($userBase, $useWhere['base']);
            $base      = array_merge($base, $userBase);
            $baseArr[] = $base;
            //Sku数据整理
            $sku    = $val['sku'];
            $sku    = Arr::pushArr($userBase, $sku);
            $skuArr = array_merge($skuArr, $sku);
        }
        unset($base, $useWhere, $userBase, $sku, $OnlyNumber);
        return ['base' => $baseArr, 'sku' => $skuArr];

    }

    /**
     * @DOC 检测商品是否存在
     */
    public function checkGoodsCode(array $goodsCode)
    {
        $goodsBaseDb   = $this->getGoodsBase($goodsCode);
        $goodsCodeData = !empty($goodsBaseDb) ? array_column($goodsBaseDb, 'goods_code') : [];
        if (!empty($goodsCodeData)) {
            throw new HomeException('商品货号： ' . implode(',', $goodsCodeData) . " 已存在，请检查", 201);
        }
        return $goodsBaseDb;
    }

    /**
     * @DOC 根据Code获取数据
     */
    public function getGoodsBase(array $goodsCode): array
    {
        $useWhere = $this->useWhere();
        //检查商品是否存在
        $goodsBaseDb = [];
        if (!empty($goodsCode)) {
            $goodsCode   = array_unique($goodsCode);
            $goodsBaseDb = GoodsBaseModel::where('member_uid', $useWhere['base']['member_uid'])
                ->whereIn('goods_code', $goodsCode)
                ->select(['goods_code', 'goods_base_id'])->get()->toArray();
        }
        return $goodsBaseDb;
    }

    /**
     * @DOC 数据整理
     */
    public function handleData(array $member, array $params, array $goodsBaseDb, string $goods_base_id)
    {
        $base = $params;
        unset($base['sku'], $base['cc'], $base['bc']);
        $member_uid       = $goodsBaseDb['member_uid'] ?? $member['uid'];
        $parent_join_uid  = $goodsBaseDb['parent_join_uid'] ?? $member['parent_join_uid'];
        $parent_agent_uid = $goodsBaseDb['parent_agent_uid'] ?? $member['parent_agent_uid'];
        $goods_base_id    = $goodsBaseDb['goods_base_id'] ?? $goods_base_id;

        $base['goods_base_id']    = $goods_base_id;
        $base['member_uid']       = $member_uid;
        $base['parent_join_uid']  = $parent_join_uid;
        $base['parent_agent_uid'] = $member['parent_agent_uid'];
        //补充数据
        // 优化后：使用 array_map 简化循环
        $params['sku'] = array_map(function ($sku) use ($goods_base_id, $member_uid, $parent_join_uid, $parent_agent_uid) {
            return array_merge($sku, [
                'goods_base_id'    => $goods_base_id,
                'member_uid'       => $member_uid,
                'parent_join_uid'  => $parent_join_uid,
                'parent_agent_uid' => $parent_agent_uid,
            ]);
        }, $params['sku']);

        $cc = $bc = $sku = [];
        if (isset($params['cc_checked']) && $params['cc_checked'] == 1) {
            $cc                    = $params['cc'];
            $cc['goods_base_id']   = $goodsBaseDb['goods_base_id'] ?? $goods_base_id;
            $handle['cc']          = $cc;
            $base['record_status'] = 1; //已经维护
        }
        if (isset($params['bc_checked']) && $params['bc_checked'] == 1) {
            $bc                    = $params['bc'];
            $bc['goods_base_id']   = $goodsBaseDb['goods_base_id'] ?? $goods_base_id;
            $handle['bc']          = $bc;
            $base['record_status'] = 1; //已经维护
        }
        $handle['base']    = $base;
        $md5GoodsBase      = make(GoodsRecordService::class)->goods_md5_key(base: $base, sku: $params['sku'], cc: $cc, bc: $bc);
        $base['goods_md5'] = $md5GoodsBase;
        //当前数据与数据库对比是否修改
        if (Arr::hasArr($goodsBaseDb, 'goods_md5')) {
            if ($md5GoodsBase == $goodsBaseDb['goods_md5']) {
                unset($base['record_status']);
            } else {
                $base['record_status'] = 1;
            }
        }
        if (isset($params['record_status']) && in_array($params['record_status'], [3, 4])) {
            $base['record_status'] = $params['record_status'];
        }
        $handle['base'] = $base;
        $handle['sku']  = $this->handleSku($params, $goodsBaseDb, $goods_base_id);
        return $handle;
    }


    /**
     * @DOC sku数据整理
     */
    public function handleSku(array $param, $goodsBaseDb, $goods_base_id)
    {
        $insert = $update = $sku_id_arr = $delArr = [];
        if (Arr::hasArr($goodsBaseDb, 'sku')) {
            $sku_id_arr = array_column($goodsBaseDb['sku'], 'sku_id');
            $delArr     = $sku_id_arr;
        }
        foreach ($param['sku'] as $key => &$val) { // 使用引用传递
            // 预处理空值 sku_id
            if (isset($val['sku_id']) && (trim($val['sku_id']) === '' || $val['sku_id'] == 0)) {
                unset($val['sku_id']);
            }
            if (isset($val['sku_id']) && in_array($val['sku_id'], $sku_id_arr)) {
                $update[] = $val;
                Arr::del($delArr, $val['sku_id']);
            } else {

                $insert[] = $val; // 空 sku_id 自动归类为新增
            }
        }
        unset($val); // 销毁引用
        $handle['insert'] = $insert;
        $handle['update'] = $update;
        $handle['del']    = $delArr;
        return $handle;
    }

    /**
     * @DOC 检测商品规格表，对应信息是否存在
     */
    public function checkSkuBarcode(array $skuCode, array $barCode)
    {
        $useWhere      = $this->useWhere();
        $skuWhere      = [];
        $skuCodeWhere  = $barCodeWhere = [];
        $goodsSkuDb    = GoodsSkuModel::where($useWhere['where'])
            ->where(function ($query) use ($skuCode, $barCode) {
                $query->orWhere('sku_code', 'in', $skuCode)->orWhere('barcode', 'in', $barCode);
            })->select(['sku_code', 'barcode'])->get()->toArray();
        $skuCodeRepeat = $barCodeRepeat = [];
        if (!empty($goodsSkuDb)) {
            $skuCodeDb     = array_column($goodsSkuDb, 'sku_code');
            $barCodeDb     = array_column($goodsSkuDb, 'barcode');
            $skuCodeDb     = array_merge($skuCode, $skuCodeDb);
            $barCodeDb     = array_merge($barCode, $barCodeDb);
            $skuCodeRepeat = Arr::fetchRepeatInArray($skuCodeDb);
            $barCodeRepeat = Arr::fetchRepeatInArray($barCodeDb);
        }
        unset($goodsSkuDb, $skuCodeDb, $skuCode, $skuCodeWhere, $skuWhere, $barCode, $barCodeDb, $barCodeWhere);
        if (!empty($skuCodeRepeat)) {
            $skuCodeRepeat = array_unique($skuCodeRepeat);
            throw new HomeException('商品编码： ' . implode(',', $skuCodeRepeat) . " 已存在，请检查", 201);
        }
        if (!empty($barCodeRepeat)) {
            $barCodeRepeat = array_unique($barCodeRepeat);
            throw new HomeException('商品条码： ' . implode(',', $barCodeRepeat) . " 已存在，请检查", 201);
        }
    }

    /*************************************************************************************/
    /**
     * @DOC 提交申请
     */
    #[RequestMapping(path: 'apply', methods: 'post')]
    public function apply(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'goods_base_id' => ['required', 'array'],
        ], [
            'goods_base_id.required' => '商品ID错误',
            'goods_base_id.array'    => '商品ID错误',
        ]);

        $useWhere              = $this->useWhere();
        $data['record_status'] = 2;
        $goods                 = GoodsBaseModel::where($useWhere['where'])
            ->whereIn('goods_base_id', $param['goods_base_id'])
            ->update($data);
        if ($goods) {
            return $this->response->json(['code' => 200, 'msg' => '提交成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '提交失败', 'data' => []]);
    }

    /*************************************************************************************/
    /**
     * @DOC 上传查验，提交之前，检查当前条码 货号是否提交上传
     */
    #[RequestMapping(path: 'examine', methods: 'post')]
    public function examine(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $validation    = $this->examineRule();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        foreach ($param as $item) {
            $LibValidation->validate($item, $validation['rule'], $validation['message']);
        }
        $useWhere  = $this->useWhere();
        $goodsCode = array_column($param, 'goods_code');
        $skuCode   = array_column($param, 'sku_code');
        $barCode   = array_column($param, 'barcode');
        $barCode   = Arr::delEmpty($barCode);
        //检测重复
        $barCodeRepeat = Arr::fetchRepeatInArray($barCode);
        $skuCodeRepeat = Arr::fetchRepeatInArray($skuCode);

        $goodsBaseModel = new GoodsBaseModel();
        $goodsBaseDb    = [];
        if (!empty($goodsCode)) {
            $goodsBaseDb = $goodsBaseModel->whereIn('goods_code', $goodsCode)->where($useWhere['where'])->select(['goods_code'])->get()->toArray();
            $goodsBaseDb = !empty($goodsBaseDb) ? array_column($goodsBaseDb, 'goods_code') : [];
        }
        $goodsSkuModel = new GoodsSkuModel();
        if (!empty($skuCode)) {
            $goodsSkuModel = $goodsSkuModel->whereIn('sku_code', $skuCode);
        }
        if (!empty($barCode)) {
            $goodsSkuModel = $goodsSkuModel->whereIn('barcode', $barCode);
        }
        $goodsSkuDb   = $goodsSkuModel->select(['sku_code', 'barcode'])->where($useWhere['where'])->get()->toArray();
        $skuCodeDbArr = $barCodeDbArr = [];
        if (!empty($goodsSkuDb)) {
            $skuCodeDbArr = array_column($goodsSkuDb, 'sku_code');
            $barCodeDbArr = array_column($goodsSkuDb, 'barcode');
        }
        //检查输入的商品分类是否正确，并返回正确的商品分类
        $categoryDb = $this->category($param);
        $brandDb    = $this->brand($param);
        unset($skuCode, $barCode);

        foreach ($param as $key => $val) {
            $error = $result = [];
            if (in_array($val['goods_code'], $goodsBaseDb)) {
                $error['goods_code_check'] = '已存在';
            }
            if (in_array($val['sku_code'], $skuCodeDbArr)) {
                $error['sku_code_check'] = '已存在';
            }
            if (in_array($val['barcode'], $barCodeDbArr)) {
                $error['barcode_check'] = '已存在';
            }
            $data['category_item_id'] = 0;
            if (Arr::hasArr($val, 'category_item')) {
                $category_item = Str::trim($val['category_item']);
                if (isset($categoryDb[$category_item])) {
                    $data['category_item_id'] = $categoryDb[$category_item]->id;
                    unset($error['category_item_id']);
                }
            }
            //检测重复
            if (in_array($val['barcode'], $barCodeRepeat)) {
                $error['barcode_repeat'] = '已重复';
            }
            if (in_array($val['sku_code'], $skuCodeRepeat)) {
                $error['sku_code_repeat'] = '已重复';
            }
            unset($data['brand']);
            if (isset($val['brand']) && !empty($val['brand'])) {
                $data['brand'] = $this->checkBrand($brandDb, $val['brand']);
            }
            $v['error']            = $error;
            $v['data']             = $data;
            $param[$key]['result'] = $v;
        }
        return $this->response->json(['code' => 200, 'msg' => '查询成功', 'data' => $param]);
    }

    /**
     * @DOC 上传查验 验证规则
     */
    public function examineRule()
    {

        $rule = [
            'goods_code'    => ['required', 'min:5'],
            'sku_code'      => ['required', 'min:3'],
            'barcode'       => ['required', 'min:10'],
            'category_item' => ['required', 'min:1'],
        ];

        $message = [
            'goods_code.required'    => '货号必填',
            'goods_code.min'         => '货号至少5位',
            'sku_code.required'      => '编码必填',
            'sku_code.min'           => '编码至少3位',
            'barcode.required'       => '条形码必填',
            'barcode.min'            => '条形码至少10位',
            'category_item.required' => '商品分类必填',
            'category_item.min'      => '商品分类不小于1',
        ];
        return ['rule' => $rule, 'message' => $message];
    }

    /**
     * @DOC 返回选择的商品分类
     */
    protected function category($param)
    {
        $category_item       = array_column($param, 'category_item');
        $category_item       = Arr::delEmpty($category_item);
        $category_item       = array_unique($category_item);
        $goodsCategory       = Db::table('record_category_goods')->whereIn('goods_name', $category_item)->select(['id', 'goods_name'])->get()->toArray();
        $goodsCategoryItemDb = array_column($goodsCategory, null, 'goods_name');
        return $goodsCategoryItemDb;
    }

    /**
     * @DOC 品牌分析
     */
    protected function brand($param)
    {
        $brand = array_column($param, 'brand');
        $brand = Arr::delEmpty($brand);
        if (!empty($brand)) {
            return BrandModel::where(function ($query) use ($brand) {
                $query->orWhereIn('source_name', $brand)
                    ->orWhereIn('brand_name', $brand)
                    ->orWhereIn('brand_en_name', $brand);
            })->select(['brand_id', 'source_name', 'brand_name', 'brand_en_name'])->get()->toArray();
        }
        return [];
    }

    /**
     * @DOC 品牌检查，返回品牌数据
     */
    protected function checkBrand(array $brandDb, string $brand)
    {
        $brand = strtolower($brand);
        foreach ($brandDb as $key => $val) {
            foreach ($val as $k => $v) {
                if (strtolower($v) == $brand) {
                    return $val;
                }
            }
        }
        return [];
    }

    /*************************************************************************************/

    /**
     * @DOC 获取模板
     */
    #[RequestMapping(path: 'template', methods: 'post')]
    public function template(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($param, [
            'id' => ['required'],
        ], [
            'id.required' => '模板ID错误',
        ]);
        $data          = make(CategoryTemplateService::class)->template(category_id: $param['id']);
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data['template']]);
    }

    /*************************************************************************************/

    /**
     * @DOC 备案列表
     */
    #[RequestMapping(path: 'record', methods: 'post')]
    public function record(RequestInterface $request): ResponseInterface
    {
        $param          = $request->all();
        $member         = $request->UserInfo;
        $useWhere       = $this->useWhere();
        $goodsBaseModel = GoodsBaseModel::query()->where($useWhere['where'])->where('parent_agent_uid', $member['parent_agent_uid']);
        $goodsSkuModel  = GoodsSkuModel::query();

        if (Arr::hasArr($param, 'category_item_id')) {
            $goodsBaseModel = $goodsBaseModel->where('category_item_id', $param['category_item_id']);
        }
        if (Arr::hasArr($param, 'goods_code')) {
            $goodsBaseModel = $goodsBaseModel->where('goods_code', 'like', '%' . $param['goods_code'] . '%');
        }
        if (Arr::hasArr($param, 'name_keyword')) {
            $goodsBaseModel = $goodsBaseModel->where(function ($query) use ($param) {
                foreach (['goods_name', 'short_name', 'short_name'] as $field) {
                    $query->orWhere($field, 'like', '%' . $param['name_keyword'] . '%');
                }
            });
        }
        if (Arr::hasArr($param, 'brand_keyword')) {
            $goodsBaseModel = $goodsBaseModel->where(function ($query) use ($param) {
                foreach (['brand_name', 'brand_en'] as $field) {
                    $query->orWhere($field, 'like', '%' . $param['brand_keyword'] . '%');
                }
            });
        }
        if (Arr::hasArr($param, 'record_status')) {
            $goodsBaseModel = $goodsBaseModel->where('record_status', $param['record_status']);
        }
        if (Arr::hasArr($param, 'cc_checked')) {
            $goodsBaseModel = $goodsBaseModel->where('cc_checked', $param['cc_checked']);
        }
        if (Arr::hasArr($param, 'bc_checked')) {
            $goodsBaseModel = $goodsBaseModel->where('bc_checked', $param['bc_checked']);
        }
        if (Arr::hasArr($param, 'record_status')) {
            $goodsBaseModel = $goodsBaseModel->where('record_status', $param['record_status']);
        }
        if (Arr::hasArr($param, 'member_uid')) {
            $goodsBaseModel = $goodsBaseModel->where('member_uid', $param['member_uid']);
            $goodsSkuModel  = $goodsSkuModel->where('member_uid', $param['member_uid']);
        }
        if (Arr::hasArr($param, 'parent_join_uid')) {
            $goodsBaseModel = $goodsBaseModel->where('parent_join_uid', $param['parent_join_uid']);
            $goodsSkuModel  = $goodsSkuModel->where('parent_join_uid', $param['parent_join_uid']);
        }
        $baseIdArr = $goodsBaseModel->pluck('goods_base_id');
        if (!empty($baseIdArr)) {
            $goodsSkuModel = $goodsSkuModel->whereIn('goods_base_id', $baseIdArr);
        } else {
            return $this->response->json(['code' => 200, 'msg' => '未查询到信息', 'data' => []]);
        }
        $data = $goodsSkuModel->with(['goods' => function ($query) {
            $query->with(['category']);
        }])->paginate($param['limit'] ?? 20);
        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items(),
            ]]);
    }

    /***********************************************************************************************************/
    /**
     * @DOC   :检测商品编码在当前用户下是否唯一
     * @Name  : checkBaseCodeUnique
     * @Author: wangfei
     * @date  : 2025-03 10:07
     * @param array $member
     * @param int $goods_base_id
     * @param $goodsCode
     * @return array
     *
     */
    protected function checkBaseCodeUnique(array $member, int|string $goods_base_id, $goodsCode)
    {
        // 处理新增场景的编码重复校验
        if (empty($goods_base_id)) {
            $exists = GoodsBaseModel::where('member_uid', $member['uid'])
                ->where('goods_code', $goodsCode)
                ->exists();
            if ($exists) {
                throw new HomeException('商品编码已存在');
            }
            return [];
        }

        // 处理编辑场景的编码重复校验
        $goodsBase = GoodsBaseModel::with(['sku'])
            ->where('goods_base_id', $goods_base_id)
            ->firstOrFail();
        $exists    = GoodsBaseModel::where('member_uid', $goodsBase->member_uid)
            ->where('goods_code', $goodsCode)
            ->where('goods_base_id', '!=', $goods_base_id)
            ->exists();

        if ($exists) {
            throw new HomeException('商品编码重复');
        }
        return $goodsBase->toArray();
    }


    /**
     * @DOC   : 检测sku的条码是否重复
     * @Name  : checkSkuCodeUnique
     * @Author: wangfei
     * @date  : 2025-03 10:20
     * @param int $source_member_uid
     * @param int|string $goods_base_id
     * @param array $skuCode
     * @return void
     *
     */
    protected function checkSkuCodeUnique(int $source_member_uid, int|string $goods_base_id, array $skuCode)
    {
        if (empty($skuCode)) return;
        $query = GoodsSkuModel::where('member_uid', $source_member_uid)
            ->whereIn('sku_code', $skuCode);
        // 编辑时排除当前商品
        if (!empty($goods_base_id)) {
            $query->where('goods_base_id', '!=', $goods_base_id);
        }
        $exists = $query->pluck('sku_code')->unique()->toArray();
        if (!empty($exists)) {
            $duplicates = array_intersect($skuCode, $exists);
            throw new HomeException(
                'SKU编码重复：' . implode(',', array_unique($duplicates)) .
                '，请修改后提交'
            );
        }
    }

    /**
     * @DOC   : 检测条码是否重复
     * @Name  : checkBarCodeUnique
     * @Author: wangfei
     * @date  : 2025-03 10:29
     * @param int $source_member_uid
     * @param int|string $goods_base_id
     * @param array $barCode
     * @return void
     */
    protected function checkBarCodeUnique(int $source_member_uid, int|string $goods_base_id, array $barCode)
    {
        if (empty($barCode)) return;
        $query = GoodsSkuModel::where('member_uid', $source_member_uid)
            ->whereIn('barcode', $barCode);
        // 编辑时排除当前商品
        if (!empty($goods_base_id)) {
            $query->where('goods_base_id', '!=', $goods_base_id);
        }
        $exists = $query->pluck('sku_code')->unique()->toArray();
        if (!empty($exists)) {
            $duplicates = array_intersect($barCode, $exists);
            throw new HomeException(
                'BarCode 编码重复：' . implode(',', array_unique($duplicates)) .
                '，请修改后提交'
            );
        }

    }

    /**
     * @DOC 提交审核
     */
    #[RequestMapping(path: 'check', methods: 'post')]
    public function check(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $member = $request->UserInfo;
        if (!Arr::hasArr($params, ['category_item_id'])) {
            throw new HomeException('请选择商品分类');
        }
        $CategoryTemplate   = make(CategoryTemplateService::class)->template(category_id: $params['category_item_id']);
        $TemplateValidation = $CategoryTemplate['validation'];
        $validationRule     = $this->validationRule();
        $rule               = array_merge_recursive($validationRule['rule'], $TemplateValidation['rule']);
        $messages           = array_merge_recursive($validationRule['messages'], $TemplateValidation['messages']);
        $params             = make(LibValidation::class)->validate($params, $rule, $messages);
        $goods_base_id      = $params['goods_base_id'] ?? '';
        $baseDbResult       = $this->checkBaseCodeUnique(member: $member, goods_base_id: $goods_base_id, goodsCode: $params['goods_code']);
        $SkuCode            = array_column($params['sku'], 'sku_code');
        $SkuCode            = Arr::delEmpty($SkuCode);
        $this->checkSkuCodeUnique(source_member_uid: $baseDbResult['member_uid'], goods_base_id: $goods_base_id, skuCode: $SkuCode);
        $barCode = array_column($params['sku'], 'barcode');
        $barCode = Arr::delEmpty($barCode);
        $this->checkBarCodeUnique(source_member_uid: $baseDbResult['member_uid'], goods_base_id: $goods_base_id, barCode: $barCode);


        $result['code'] = 201;
        $result['msg']  = '操作失败';
        $result['data'] = [];
        $handleData     = $this->handleData($member, $params, $baseDbResult, $goods_base_id);
        //  return $this->response->json($handleData);

        Db::beginTransaction();
        try {
            Db::table('goods_base')->where('goods_base_id', '=', $params['goods_base_id'])->update($handleData['base']);
            if (!empty($handleData['cc'])) {
                Db::table('goods_cc')->where('goods_base_id', '=', $params['goods_base_id'])->update($handleData['cc']);
            }
            if (!empty($handleData['bc'])) {
                Db::table('goods_bc')->where('goods_base_id', '=', $params['goods_base_id'])->update($handleData['bc']);
            }
            //批量更新Sku
            if (Arr::hasArr($handleData['sku'], 'update')) {
                foreach ($handleData['sku']['update'] as $k => $v) {
                    Db::table('goods_sku')->where('sku_id', '=', $v['sku_id'])->update($v);
                }
            }

            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '操作成功';
        } catch (\Exception $e) {
            Db::rollback();
            $result['msg'] = $result['msg'] . $e->getMessage();
        }

        // 这里使用协程调用
        if ($params['record_status'] == 3) { // 3 是通过审核
            $goods_base_id = $request->all()['goods_base_id'];
            $member        = $request->UserInfo;
            go(function () use ($goods_base_id, $member) {
                $result = make(GoodsRecordService::class)->localRecordToTargetData(source_base_id: $goods_base_id, member: $member);
                // 记录日志或进行其他处理
                if ($result['code'] == 201) {
                    Logger::error('备案通过提交到官方库：', $result);
                }
            });
        }

        return $this->response->json($result);
    }


}
