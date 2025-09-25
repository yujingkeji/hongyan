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
use Hyperf\Validation\Rule;
use function App\Common\batchUpdateSql;

#[Controller(prefix: '/base/goods/template/field', server: 'httpAdmin')]
class GoodsTemplateFieldController extends AdminBaseController
{
    #[Inject]
    protected BaseServiceInterface $baseService;


    /**
     * @DOC 属性字段列表
     */
    #[RequestMapping(path: 'lists', methods: 'post')]
    public function fieldLists(RequestInterface $request)
    {
        $param = $request->all();
        $where = [];
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['field_name', 'like', '%' . $param['keyword'] . '%'];
        }
        $data           = GoodsTemplateFieldModel::where($where)->paginate($param['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items()
        ];

        return $this->response->json($result);
    }

    /**
     * @DOC   : add
     * @Name  : fieldHandle
     * @Author: wangfei
     * @date  : 2025-04 15:27
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     *
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request)
    {
        $result['code']     = 201;
        $result['msg']      = '处理失败';
        $params             = $request->all();
        $params             = make(LibValidation::class)->validate($params,
            [
                'field'          => ['required', 'unique:goods_template_field,field'], //字段标识，数据库里的字段名称
                'field_name'     => ['required', 'max:20'],//字段中文名称
                'field_type'     => ['required'], //字段型 1：文本框 2：选择框
                'field_text'     => ['required'], //文本域 当是选择框（2）时，默认一行一条，转成对象。当为1：文本框时候，文本域  若次字段有值，即为默认值。
                'field_width'    => ['required', 'integer'],//输入框宽度
                'field_required' => ['integer'],// '是否必填 0：非必填，1：必填',
                'is_other'       => ['nullable', 'integer', 'in:0,1'], //是否开启 "其他" 输入
                'info'           => ['string', 'nullable']
            ],
            [
                'field.required'       => '请输入字段标识',
                'field.unique'         => '字段标识已存在',
                'field_name.required'  => '请输入字段名称',
                'field_name.max'       => '字段名称不能超过20',
                'field_type.required'  => '请选择字段类型',
                'field_text.required'  => '请输入字段默认值',
                'field_width.required' => '请输入字段宽度',
                'field_width.integer'  => '字段宽度必须为整数',
                'is_other.string'      => '请输入是否开启其他输入',
                'info.string'          => '请输入字段描述'
            ]

        );
        $params['add_time'] = time();
        if (Db::table('goods_template_field')->insert($params)) {
            $result['code'] = 200;
            $result['msg']  = "保存成功";
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   :删除
     * @Name  : delete
     * @Author: wangfei
     * @date  : 2025-04 11:41
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     *
     */
    #[RequestMapping(path: 'delete', methods: 'post')]
    public function delete(RequestInterface $request)
    {
        throw new HomeException(201, '暂不支持删除');
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $params         = make(LibValidation::class)->validate($params,
            [
                'field_id' => ['required', 'integer', 'exists:goods_template_field,field_id']
            ],
            [
                'field_id.required' => '请输入需要删除的属性字段ID'
            ]

        );
        if (Db::table('goods_template_field')->where('field_id', '=', $params['field_id'])->delete()) {
            $result['code'] = 200;
            $result['msg']  = "删除成功";
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   : edit
     * @Name  : fieldHandle
     * @Author: wangfei
     * @date  : 2025-04 15:27
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     *
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request)
    {
        $result['code'] = 201;
        $result['msg']  = '处理失败';
        $params         = $request->all();
        $params         = make(LibValidation::class)->validate($params,
            [
                'field_id' => ['required', 'integer', 'exists:goods_template_field,field_id'],

                'field' =>
                    [
                        'required',
                        Rule::unique('goods_template_field')->ignore($params['field_id'], 'field_id')
                    ], //字段标识，数据库里的字段名称，排除当前ID

                'field_name'     => ['required', 'max:20'],//字段中文名称
                'field_type'     => ['required'], //字段型 1：文本框 2：选择框
                'field_text'     => ['required'], //文本域 当是选择框（2）时，默认一行一条，转成对象。当为1：文本框时候，文本域  若次字段有值，即为默认值。
                'field_width'    => ['required', 'integer'],//输入框宽度
                'field_required' => ['integer'],//是否必填 0：非，1：必
                'is_other'       => ['nullable', 'integer', 'in:0,1'], //是否开启 "其他" 输入
                'info'           => ['string', 'nullable']
            ],
            [
                'field_id.required'   => '请输入字段ID',
                'field.required'      => '请输入字段标识',
                'field.unique'        => '字段标识已存在',
                'field_name.required' => '请输入字段名称',
                'field_name.max'      => '字段名称不能超过20',
                'field_type.required' => '请选择字段类型',
                'field_text.required' => '请输入字段默认值',

                'is_other.string' => '请输入是否开启其他输入',
                'info.string'     => '请输入字段描述'
            ]

        );
        if (Db::table('goods_template_field')->where('field_id', $params['field_id'])->update($params)) {
            $result['code'] = 200;
            $result['msg']  = "修改成功";
        }
        return $this->response->json($result);
    }
}
