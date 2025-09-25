<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\JsonRpc\BaseServiceInterface;
use App\Model\GoodsCategoryItemModel;
use App\Model\GoodsCategoryModel;
use App\Model\GoodsTemplateFieldModel;
use App\Model\GoodsTemplateItemModel;
use App\Model\GoodsTemplateModel;
use App\Model\RecordCategoryGoodsModel;
use App\Model\TemplateCategoryModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseEditUpdateCacheService;
use App\Service\GoodsService;

use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use function App\Common\batchUpdateSql;

#[Controller(prefix: '/base/goods/template', server: 'httpAdmin')]
class GoodsTemplateController extends AdminBaseController
{
    #[Inject]
    protected BaseServiceInterface $baseService;

    #[Inject]
    protected GoodsService $goodsService;


    /**
     * 属性模板列表
     */
    #[RequestMapping(path: 'lists', methods: 'post')]
    public function GoodsTemplateLists(RequestInterface $request)
    {
        $params = $request->all();
        $where  = [];
        if (Arr::hasArr($params, 'keyword')) {
            $where[] = ['template_name', 'like', '%' . $params['keyword'] . '%'];
        }
        $data           = GoodsTemplateModel::with(['item'])->where($where)->paginate($params['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg']  = '查询成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items(),
        ];
        return $this->response->json($result);
    }

    #[RequestMapping(path: 'handle', methods: 'post')]
    public function templateHandle(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $params = make(LibValidation::class)->validate($params,
            [
                'template_id'           => ['integer'],
                'template_name'         => ['required', 'string'],
                'info'                  => ['string'],
                'item.*.item_id'        => ['integer', 'nullable'], //字段
                'item.*.field'          => ['required', 'string'], //字段
                'item.*.field_name'     => ['required', 'string'], //字段名称
                'item.*.field_type'     => ['required', 'integer', 'in:1,2'], // 字段类型 1：文本框 2：选择框
                'item.*.field_text'     => ['required_if:field_type,2', 'string', 'nullable'], //当是选择框（2）时，默认一行一条，转成对象。当为1：文本框时候，若次字段有值，即为默认值。
                'item.*.field_width'    => ['integer', 'nullable'], //文本框宽度
                'item.*.field_required' => ['integer', 'nullable', 'in:0,1'], //文本框宽度
                'item.*.is_other'       => ['integer', 'nullable'], //'0:不开启  1：开启其他输入'
                'item.*.sort'           => ['integer', 'nullable'], //排序
                'item.*.complex'        => ['integer', 'nullable'], //当大于0时，值相同时，组合一列。
            ],
            [
                'template_id.integer'    => '请选择模板',
                'template_name.required' => '请输入模板名称',
                'template_name.string'   => '请输入模板名称',
                'info.string'            => '请输入模板描述',
                'item.*.field.required'  => '请输入字段',
            ]

        );


        $handleTemplate  = $this->handleTemplate($params);//价格模板的具体区域内容
        $item_update_sql = '';
        if (!empty($handleTemplate['item_update'])) {
            $item_update     = $handleTemplate['item_update'];
            $item_update_sql = batchUpdateSql('goods_template_item', $item_update, 'item_id');
        }
        //   return $this->response->json($handleTemplate);
        //版本具体价格
        Db::beginTransaction();
        try {

            if (!empty($handleTemplate['template_update'])) {
                Db::table("goods_template")->where('template_id', '=', $handleTemplate['template_id'])->update($handleTemplate['template_update']);
                $template_id = $handleTemplate['template_id'];
            }
            if (!empty($handleTemplate['template_insert'])) {
                $template_id = Db::table("goods_template")->insertGetId($handleTemplate['template_insert']);
            }
            //批量删除
            if (!empty($handleTemplate['item_del'])) {
                Db::table("goods_template_item")->where('template_id', '=', $handleTemplate['template_id'])->whereIn('item_id', $handleTemplate['item_del'])->delete();
            }

            if (!empty($handleTemplate['item_insert'])) {
                $item_insert        = $handleTemplate['item_insert'];
                $Add['template_id'] = $template_id;
                $Add['add_time']    = time();
                $item_insert        = Arr::pushArr($Add, $item_insert);
                Db::table("goods_template_item")->insert($item_insert);
            }
            if (!empty($item_update_sql)) {
                Db::update($item_update_sql);
            }

            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '操作成功';
        } catch (\Throwable $e) {
            Db::rollback();
            $result['code'] = 201;
            $result['msg']  = '添加版本失败：' . $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   : 整理模板明细
     * @Name  : handleTemplate
     * @Author: wangfei
     * @date  : 2025-04 16:04
     * @param $params
     * @return array
     */
    protected function handleTemplate(array $params)
    {
        $template_id   = 0;
        $TemplateDb    = $template_update = $template_insert = [];
        $haveItemIDArr = [];//存在的ItemID集合
        if (!empty($params['template_id'])) {
            $TemplateDb = GoodsTemplateModel::query()->with(['item'])->where('template_id', $params['template_id'])->first();
            if (!empty($TemplateDb)) {
                $TemplateDb                           = $TemplateDb->toArray();
                $template_id                          = $TemplateDb['template_id'];
                $template_update['template_id']       = $template_id;
                $template_update['target_country_id'] = $params['country_id'] ?? 1;
                $template_update['template_name']     = $params['template_name'];
                $template_update['info']              = $params['info'] ?? '';
                if (!empty($TemplateDb['item'])) {
                    $haveItemIDArr = array_column($TemplateDb['item'], 'item_id');
                }
            }
        } else {
            $template_insert['target_country_id'] = $params['country_id'] ?? 1;
            $template_insert['template_name']     = $params['template_name'];
            $template_insert['info']              = $params['info'] ?? '';
        }


        $item_insert = $item_update = [];
        $delItem     = $haveItemIDArr;
        $beFieldArr  = [];//判断字段是否存在，默认为空
        foreach ($params['item'] as $Key => $Val) {
            //不存在添加进去,存在抛出异常
            if (in_array($Val['field'], $beFieldArr)) {
                throw new HomeException('模板中，字段' . $Val['field'] . '不能重复，否则造成goods_sku 数据错误');
            } else {
                $beFieldArr[] = $Val['field'];
            }
            /*******************************************/
            if (isset($Val['item_id']) && in_array($Val['item_id'], $haveItemIDArr)) {
                $Val['item_id'] = $Val['item_id'];
                $item_update[]  = $Val;
                Arr::del($delItem, $Val['item_id']);//删除需要更新的值
            } else {
                unset($Val['item_id']);
                $item_insert[] = $Val;
            }
        }
        $result['template_id']     = $template_id;
        $result['template_insert'] = $template_insert;
        $result['template_update'] = $template_update;
        $result['item_insert']     = $item_insert;
        $result['item_update']     = $item_update;
        $result['item_del']        = $delItem;
        return $result;
    }
}
