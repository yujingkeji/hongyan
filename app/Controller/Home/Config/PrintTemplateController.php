<?php

namespace App\Controller\Home\Config;

use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\ApiPlatformModel;
use App\Model\PrintTemplateModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use App\Service\PrintService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "config/print")]
class PrintTemplateController extends AbstractController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;

    protected array $templateType; //模板类型缓存

    public function __construct()
    {
        parent::__construct();
        $this->templateType = $this->baseCacheService->ConfigCache(model: 28000);
    }

    /**
     * @DOC 打印模板列表
     */
    #[RequestMapping(path: 'index', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $params        = $request->all();
        $userInfo      = $this->request->UserInfo;
        $template_type = array_column($this->templateType, 'cfg_id');
        $params        = make(LibValidation::class)->validate($params, [
            'temp_type'     => [Rule::in($template_type)],
            'status'        => [Rule::in([0, 1])],
            'platform_id'   => ['integer'],
            'template_name' => ['string'],
            'page'          => ['integer'],
            'limit'         => ['integer'],
        ]);
        $result        = \Hyperf\Support\make(PrintService::class)->getTemplateList($params, $userInfo);
        return $this->response->json($result);
    }


    /**
     * @DOC 打印模板新增
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $data             = $this->getData($param, $member);
        $data['add_time'] = $data['update_time'] = time();
        PrintTemplateModel::insert($data);
        return $this->response->json(['code' => 200, 'msg' => '新增成功', 'data' => []]);
    }

    /**
     * @DOC 打印模板修改
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $data                = $this->getData($param, $member, 'edit');
        $data['update_time'] = time();
        PrintTemplateModel::where('template_id', $param['template_id'])->update($data);
        return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
    }

    /**
     * @DOC 打印模板逻辑处理
     */
    protected function getData($param, $member, $type = 'add'): array
    {
        $rule    = [
            'template_id'   => ['required', 'integer'],
            'template_name' => ['required', 'min:2', Rule::unique('print_template')->where(function ($query) use ($param, $member, $type) {
                $query = $query->where('parent_agent_uid', '=', $member['parent_agent_uid'])
                    ->where('template_name', '=', $param['template_name']);
                if ($type == 'edit') {
                    $query = $query->whereNotIn('template_id', [$param['template_id']]);
                }
                return $query;
            })],
            'platform_id'   => ['nullable', 'integer'],
            'temp_url'      => ['required'],
            'status'        => ['required', Rule::in([1, 0])],
        ];
        $message = [
            'template_name.required' => '模板名称必填',
            'template_name.min'      => '模板名称最少两位',
            'template_name.unique'   => '模板名称已存在',
            'platform_id.required'   => '缺少物流公司',
            'platform_id.integer'    => '物流公司错误',
            'temp_url.required'      => '模板链接必填',
            'status.required'        => '状态必填',
            'status.in'              => '状态错误',
            'template_id.required'   => '缺少模板',
            'template_id.integer'    => '模板错误',
        ];
        if ($type == 'add') {
            unset($rule['template_id']);
        }
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, $rule, $message);
        $param['template_platform'] = 0;
        // 检测链接 牛栗前缀
        if (strpos($param['temp_url'], 'template.rpc.********.cn')) {
            $param['template_platform'] = 28601;
        }
        // 检测链接 菜鸟前缀
        if (strpos($param['temp_url'], 'cloudprint.cainiao.com')) {
            $param['template_platform'] = 28602;
        }
        if ($param['template_platform'] == 0) {
            throw new HomeException('模板链接错误');
        }
        // 面单必选物流公司
        if ($param['temp_type'] == 28005 && empty($param['platform_id'])) {
            throw new HomeException('缺少物流公司');
        }

        $temp_items  = json_encode([], true);
        $page_height = '';
        $page_width  = '';
        # 访问链接，获取模板数据  若获取到则是栗子模版

        switch ($param['template_platform']) {
            # 牛栗
            case 28601:
                try {
                    $tempData    = file_get_contents($param['temp_url']);
                    $tempData    = json_decode($tempData, true);
                    $temp_items  = json_encode($tempData['data'], true);
                    $page_height = $tempData['data']['height'];
                    $page_width  = $tempData['data']['width'];
                } catch (\Exception $exception) {
                    throw new HomeException('模板链接错误');
                }
                break;
            default:
                break;
        }

        $data['template_platform'] = $param['template_platform'];
        $data['member_uid']        = $member['uid'];
        $data['parent_join_uid']   = $member['parent_join_uid'];
        $data['parent_agent_uid']  = $member['parent_agent_uid'];
        $data['template_name']     = $param['template_name'];
        $data['platform_id']       = $param['platform_id'] ?? 0;
        $data['temp_url']          = $param['temp_url'];
        $data['temp_items']        = $temp_items;
        $data['page_height']       = $page_height;
        $data['page_width']        = $page_width;
        $data['status']            = $param['status'];
        $data['temp_type']         = $param['temp_type'] ?? 28005;
        return $data;
    }

    /**
     * @DOC 打印模板详情
     */
    #[RequestMapping(path: 'detail', methods: 'post')]
    public function detail(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(PrintService::class)->getPintTemplateDetail($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 打印模板删除
     */
    #[RequestMapping(path: 'del', methods: 'post')]
    public function del(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'template_id' => ['required', 'integer']
        ], [
            'template_id.required' => '模板错误',
            'template_id.integer'  => '模板错误'
        ]);
        $where    = [];
        $where[]  = [
            'template_id'      => $param['template_id'],
            'parent_join_uid'  => $member['parent_join_uid'],
            'parent_agent_uid' => $member['parent_agent_uid'],
            'member_uid'       => $member['uid'],
        ];
        $template = PrintTemplateModel::where($where)->first();
        if (empty($template)) {
            throw new HomeException('未查询到模板信息');
        }
        if ($template->status == 1) {
            throw new HomeException('删除失败，模板正在使用中');
        }
        PrintTemplateModel::where($where)->delete();
        return $this->response->json(['code' => 200, 'msg' => '删除成功', 'data' => []]);
    }

    /**
     * @DOC 打印模板状态修改
     */
    #[RequestMapping(path: 'status', methods: 'post')]
    public function status(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'template_id' => ['required', 'integer'],
            'status'      => ['required', Rule::in([0, 1])],
        ], [
            'template_id.required' => '模板错误',
            'template_id.integer'  => '模板错误',
            'status.required'      => '状态必选',
            'status.in'            => '状态错误',
        ]);

        $template = PrintTemplateModel::where('template_id', '=', $param['template_id'])
            ->where('parent_join_uid', $member['parent_join_uid'])
            ->exists();
        if (!$template) {
            throw new HomeException('未查询到模板信息');
        }
        PrintTemplateModel::where('template_id', $param['template_id'])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->update(['status' => $param['status']]);
        return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
    }

    /**
     * @DOC 打印模板设置为默认
     */
    #[RequestMapping(path: 'default', methods: 'post')]
    public function setDefault(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param, [
            'template_id' => ['required', 'integer'],
            'default'     => ['required', Rule::in([0, 1])],
        ], [
            'template_id.required' => '模板错误',
            'template_id.integer'  => '模板错误',
            'default.required'     => '默认状态必选',
            'default.in'           => '默认状态错误',
        ]);

        $print = PrintTemplateModel::where('template_id', $param['template_id'])->first();
        if (empty($print)) {
            throw new HomeException('未查询到模板信息');
        }
        $print = $print->toArray();
        if ($param['default'] == 1) {
            PrintTemplateModel::where('parent_agent_uid', $member['parent_agent_uid'])
                ->where('platform_id', $print['platform_id'])
                ->where('temp_type', $print['temp_type'])
                ->update(['default' => 0]);
        }
        PrintTemplateModel::where('template_id', $param['template_id'])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('temp_type', $print['temp_type'])
            ->update(['default' => $param['default']]);
        return $this->response->json(['code' => 200, 'msg' => '设置成功', 'data' => []]);
    }

    /**
     * @DOC 打印模版类型列表
     */
    #[RequestMapping(path: 'type/list', methods: 'post')]
    public function typeLists(RequestInterface $request)
    {
        $baseCacheService = \Hyperf\Support\make(BaseCacheService::class);
        $result['code']   = 200;
        $result['msg']    = '查询成功';
        $result['data']   = array_values($baseCacheService->ConfigPidCache(28000));
        return $this->response->json($result);
    }


}
