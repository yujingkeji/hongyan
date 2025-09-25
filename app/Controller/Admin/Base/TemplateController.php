<?php

namespace App\Controller\Admin\Base;

use App\Common\Lib\Arr;
use App\Controller\Admin\AdminBaseController;
use App\Exception\HomeException;
use App\Model\ConfigModel;
use App\Model\CountryCodeModel;
use App\Model\CsvExportTemplateItemModel;
use App\Model\CsvExportTemplateModel;
use App\Model\CustomsSupervisionModel;
use App\Model\PortModel;
use App\Model\PrintComponentModel;
use App\Model\PrintTemplateModel;
use App\Request\LibValidation;
use App\Service\ConfigService;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/', server: 'httpAdmin')]
class TemplateController extends AdminBaseController
{
    /**
     * @DOC 物流模板列表
     */
    #[RequestMapping(path: 'base/template/lists', methods: 'post')]
    public function lists(RequestInterface $request)
    {
        $param  = $request->all();
        $result = ConfigService::templateLists($param);
        return $this->response->json($result);
    }

    /**
     * @DOC 新物流模板列表
     */
    #[RequestMapping(path: 'base/template/lists/new', methods: 'post')]
    public function listsNew(RequestInterface $request)
    {
        $param          = $request->all();
        $result['code'] = 201;
        $result['msg']  = '获取失败';

        $where = [];
        if (Arr::hasArr($param, 'keyword')) {
            $where[] = ['template_name', 'like', '%' . $param['keyword'] . '%'];
        }
        try {
            $dataDb = PrintTemplateModel::where($where)->paginate($param['limit'] ?? 20);
            $lists  = $dataDb->items();
            foreach ($lists as $key => $value) {
                $lists[$key]['pageWidth']  = $value['page_width'];
                $lists[$key]['pageHeight'] = $value['page_height'];
                $lists[$key]['title']      = $value['template_name'];
                $lists[$key]['tempItems']  = json_decode($value['temp_items'], true);
                unset($lists[$key]['page_height'], $lists[$key]['page_width'], $lists[$key]['temp_items'], $lists[$key]['template_name']);
            }
            $result['code'] = 200;
            $result['msg']  = '获取成功';
            $result['data'] = [
                'total' => $dataDb->total(),
                'data'  => $lists,
            ];
        } catch (\Exception $e) {
            $result['msg'] = $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 获取所有的组件信息
     */
    #[RequestMapping(path: 'base/template/component', methods: 'post')]
    public function getComponent(RequestInterface $request)
    {
        $where   = [];
        $where[] = ['status', '=', '1'];
        $where[] = ['pid', '=', '0'];
        $dataDb  = PrintComponentModel::select(['title', 'name', 'default_value', 'type', 'component_id', 'is_value'])
            ->where($where)->get()->toArray();
        foreach ($dataDb as $key => $value) {
            $dataDb[$key]['value']        = ($value['is_value'] == 1) ? "{" . $value['title'] . "}" : '';
            $dataDb[$key]['isEdit']       = 1;
            $dataDb[$key]['defaultValue'] = $value['default_value'];
            unset($dataDb[$key]['default_value']);
            if ($value['type'] == 'braid-table') {
                $child = PrintComponentModel::select(['title', 'name'])->where(['pid' => $value['component_id']])->get()->toArray();
                foreach ($child as $i => $item) {
                    $child[$i]['value']  = "{" . $item['title'] . "}";
                    $child[$i]['isEdit'] = 1;
                }
                $dataDb[$key]['columnsAttr'] = $child;
            }
            unset($dataDb[$key]['component_id']);
        }
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $dataDb;
        return $this->response->json($result);
    }

    /**
     * @DOC 导出模板列表
     */
    #[RequestMapping(path: 'base/template/csv/lists', methods: 'get,post')]
    public function csvLists(RequestInterface $request)
    {
        $param          = $request->all();
        $data           = CsvExportTemplateModel::paginate($param['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = [
            'total' => $data->total(),
            'data'  => $data->items(),
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC 模板详情
     */
    #[RequestMapping(path: 'base/template/csv/info', methods: 'post')]
    public function csvInfo(RequestInterface $request)
    {
        $params = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'template_id' => ['required'],
            ], [
                'template_id.required' => '模板不存在',
            ]);

        $data           = CsvExportTemplateModel::with(['item'])
            ->where('template_id', $param['template_id'])->first();
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $data ?: [];
        return $this->response->json($result);
    }

    /**
     * @DOC 维护模版
     */
    #[RequestMapping(path: 'base/template/csv/handle', methods: 'post')]
    public function handle(RequestInterface $request)
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'country_id'      => ['nullable'],
                'template_name'   => ['required'],
                'template_cfg_id' => ['required'],
                'port_id'         => ['nullable'],
                'supervision_id'  => ['nullable'],
                'item'            => ['required', 'array'],
                'item.*.name'     => ['required'],
                'item.*.field'    => ['required'],
            ], [
                'template_id.required'     => '模板不存在',
                'template_name.required'   => '模板名称不能为空',
                'template_desc.required'   => '模板描述不能为空',
                'template_cfg_id.required' => '模板配置不能为空',
                'port_id.required'         => '端口不能为空',
                'supervision_id.required'  => '监督人不能为空',
                'item.required'            => '模板内容不能为空',
                'item.*.item_id.required'  => '模板内容不能为空',
                'item.*.name.required'     => '模板内容不能为空',
                'item.*.field.required'    => '模板内容不能为空',
            ]
        );
        $handelTemplate = $this->handelTemplate($param);
        //字段批量更新
        if (!empty($handelTemplate['TemplateItemUpdateData'])) {
            $TemplateItemUpdateData = $handelTemplate['TemplateItemUpdateData'];
            $CsvTemplateItemModel   = new CsvExportTemplateItemModel();
            foreach ($TemplateItemUpdateData as $value) {
                $CsvTemplateItemModel->where('item_id', $value['item_id'])->update($value);
            }
        }

        Db::beginTransaction();
        try {
            if (!empty($handelTemplate['TemplateInsertData'])) {
                $template_id = Db::table('csv_export_template')->insertGetId($handelTemplate['TemplateInsertData']);
            }
            if (!empty($handelTemplate['TemplateUpdateData'])) {
                $template_id = $param['template_id'];
                Db::table('csv_export_template')->where('template_id', '=', $template_id)->update($handelTemplate['TemplateUpdateData']);
            }
            if (!empty($handelTemplate['TemplateItemInsertData'])) {
                $TemplateItemInsertData = $handelTemplate['TemplateItemInsertData'];
                $Add['template_id']     = $template_id;
                $TemplateItemInsertData = Arr::pushArr($Add, $TemplateItemInsertData);
                Db::table('csv_export_template_item')->insert($TemplateItemInsertData);
            }
            if (!empty($handelTemplate['TemplateItemDelData'])) {
                Db::table('csv_export_template_item')->whereIn('item_id', $handelTemplate['TemplateItemDelData'])->delete();
            }
            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '添加成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 模版删除
     */
    #[RequestMapping(path: 'base/template/csv/del', methods: 'post')]
    public function del(RequestInterface $request)
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'template_id' => ['required'],
            ], [
                'template_id.required' => '模板不存在',
            ]
        );
        $template_id = $param['template_id'];
        Db::beginTransaction();
        try {
            Db::table('csv_export_template')->where('template_id', $template_id)->delete();
            Db::table('csv_export_template_item')->where('template_id', $template_id)->delete();
            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '删除成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }


    /**
     * @DOC  整理需要保存的数据
     */
    public function handelTemplate(array $params)
    {
        $TemplateInsertData     = [];
        $TemplateUpdateData     = [];
        $TemplateItemInsertData = [];
        $TemplateItemUpdateData = [];
        $TemplateItemDelData    = [];

        $template_name_deputy = '';
        $country_id           = 0;
        if (Arr::hasArr($params, 'country_id')) {
            $CountryDb = CountryCodeModel::where('country_id', '=', $params['country_id'])->first();
            if (!empty($CountryDb)) {
                $CountryDb            = $CountryDb->toArray();
                $template_name_deputy .= $CountryDb['country_name'];
                $country_id           = $CountryDb['country_id'];
            }
        }
        //口岸
        $port_id = 0;
        if (Arr::hasArr($params, 'port_id')) {
            $PortDb = PortModel::where('port_id', '=', $params['port_id'])->first();
            if (!empty($PortDb)) {
                $PortDb               = $PortDb->toArray();
                $template_name_deputy .= $PortDb['name'];
                $port_id              = $PortDb['port_id'];
            }
        }
        $cfg_id = 0;
        if (Arr::hasArr($params, 'template_cfg_id')) {
            $TemplateCfgDb = ConfigModel::where('cfg_id', '=', $params['template_cfg_id'])->first();
            if (!empty($TemplateCfgDb)) {
                $TemplateCfgDb        = $TemplateCfgDb->toArray();
                $template_name_deputy .= $TemplateCfgDb['name'];
                $cfg_id               = $TemplateCfgDb['cfg_id'];
            }
        }
        $supervision_id = 0;
        if (Arr::hasArr($params, 'supervision_id')) {
            $SupervisionDb = CustomsSupervisionModel::where('supervision_id', '=', $params['supervision_id'])->first();
            if (!empty($SupervisionDb)) {
                $SupervisionDb        = $SupervisionDb->toArray();
                $template_name_deputy .= $SupervisionDb['supervision_name'];
                $supervision_id       = $SupervisionDb['supervision_id'];;
            }
        }
        $weidu_id = 0;
        if (Arr::hasArr($params, 'weidu_id')) {
            $WeiDuDb = ConfigModel::where('cfg_id', '=', $params['weidu_id'])->first();
            if (!empty($WeiDuDb)) {
                $WeiDuDb              = $WeiDuDb->toArray();
                $template_name_deputy .= $WeiDuDb['name'];
                $weidu_id             = $WeiDuDb['cfg_id'];;
            }
        }

        // 判断模板是否存在
        $TemplateItemIdArr = [];
        if (Arr::hasArr($params, 'template_id')) {
            $TemplateDb = Db::table("csv_export_template")->where('template_id', '=', $params['template_id'])->first();
            if (empty($TemplateDb)) {
                throw new HomeException('模板不存在、禁止操作');
            }
            //不为空
            if (!empty($TemplateDb)) {
                $TemplateItemDb      = Db::table("csv_export_template_item")->where('template_id', '=', $params['template_id'])->get()->toArray();
                $TemplateItemIdArr   = array_column($TemplateItemDb, 'item_id');
                $TemplateItemDelData = $TemplateItemIdArr;
            }
        }
        $time = time();
        if (!Arr::hasArr($params, 'template_id')) {
            $TemplateInsertData['template_name']        = $params['template_name'];
            $TemplateInsertData['template_name_deputy'] = $template_name_deputy;
            $TemplateInsertData['template_cfg_id']      = $cfg_id;
            $TemplateInsertData['country_id']           = $country_id;
            $TemplateInsertData['port_id']              = $port_id;
            $TemplateInsertData['supervision_id']       = $supervision_id;
            $TemplateInsertData['weidu_id']             = $weidu_id;
            $TemplateInsertData['add_time']             = $time;
        }
        if (Arr::hasArr($params, 'template_id')) {
            $TemplateUpdateData['template_id']          = $params['template_id'];
            $TemplateUpdateData['template_name']        = $params['template_name'];
            $TemplateUpdateData['template_name_deputy'] = $template_name_deputy;
            $TemplateUpdateData['template_cfg_id']      = $cfg_id;
            $TemplateUpdateData['country_id']           = $country_id;
            $TemplateUpdateData['port_id']              = $port_id;
            $TemplateUpdateData['supervision_id']       = $supervision_id;
            $TemplateUpdateData['weidu_id']             = $weidu_id;
        }
        //整理明细
        foreach ($params['item'] as $key => $val) {
            if (Arr::hasArr($val, 'item_id')) {
                if (in_array($val['item_id'], $TemplateItemIdArr)) {
                    $item['item_id']          = $val['item_id'];
                    $item['name']             = $val['name'];
                    $item['field']            = $val['field'];
                    $item['sort']             = $val['sort'];
                    $TemplateItemUpdateData[] = $item;
                    Arr::del($TemplateItemDelData, $val['item_id']);
                }
            } else {
                unset($item);
                $item['name']             = $val['name'];
                $item['field']            = $val['field'];
                $item['sort']             = $val['sort'];
                $TemplateItemInsertData[] = $item;
            }
        }
        $result['TemplateInsertData']     = $TemplateInsertData;
        $result['TemplateUpdateData']     = $TemplateUpdateData;
        $result['TemplateItemInsertData'] = $TemplateItemInsertData;
        $result['TemplateItemUpdateData'] = $TemplateItemUpdateData;
        $result['TemplateItemDelData']    = $TemplateItemDelData;
        return $result;
    }


}
