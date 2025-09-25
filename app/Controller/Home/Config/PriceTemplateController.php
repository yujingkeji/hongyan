<?php

namespace App\Controller\Home\Config;

use App\Common\Lib\Arr;
use App\Common\Lib\TimeLib;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\FlowCheckItemModel;
use App\Model\FlowCheckModel;
use App\Model\FlowModel;
use App\Model\MemberChildModel;
use App\Model\PriceTemplateModel;
use App\Model\PriceTemplateVersionModel;
use App\Request\FlowRequest;
use App\Request\LibValidation;
use App\Request\PriceTemplateRequest;
use App\Service\Cache\BaseCacheService;
use App\Service\FlowService;
use App\Service\PriceTemplateService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: "config/price")]
class PriceTemplateController extends AbstractController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;

    /**
     * @DOC 价格模板列表
     */
    #[RequestMapping(path: 'templates', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $params           = $request->all();
        $member           = $this->request->UserInfo;
        $CountryCodeCache = $this->baseCacheService->CountryCodeCache();
        $CountryCodeCache = array_column($CountryCodeCache, 'country_id');
        $LibValidation    = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params           = $LibValidation->validate($params, [
                'page'              => ['required', 'integer'],
                'limit'             => ['required', 'integer'],
                'status'            => [Rule::in([0, 1])],
                'keyword'           => ['string'],
                'online'            => ['integer', Rule::in([0, 1])],
                'send_country_id'   => ['integer', Rule::in($CountryCodeCache)],//发出国家
                'target_country_id' => ['integer', Rule::in($CountryCodeCache)],//目的国家
            ]
        );
        $where[]          = ['member_uid', '=', $member['uid']];
        if (Arr::hasArr($params, 'keyword')) {
            $where[] = ['template_name', 'like', $params['keyword'] . '%'];
        }

        if (Arr::hasArr($params, 'send_country_id')) {
            $where[] = ['send_country_id', '=', $params['send_country_id']];
        }

        if (Arr::hasArr($params, 'target_country_id')) {
            $where[] = ['target_country_id', '=', $params['target_country_id']];
        }
        if (Arr::hasArr($params, 'status')) {
            $where[] = ['status', '=', $params['status']];
        }
        if (Arr::hasArr($params, 'online')) {
            $where[] = ['use_version', '>', 0];
        }
        $paginate       = PriceTemplateModel::query()->with(['currency', 'use', 'check', 'flow' => function ($query) {
            $query->select(['flow_id', 'flow_name']);
        }])->where($where)->orderBy('add_time', 'desc')->paginate($params['limit'] ?? 20);
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $paginate;
        return $this->response->json($result);
    }

    /**
     * @DOC 新增
     * @Name   add
     * @Author wangfei
     * @date   2023/11/2 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'templates/add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $params               = $request->all();
        $member               = $this->request->UserInfo;
        $LibValidation        = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest          = \Hyperf\Support\make(PriceTemplateRequest::class);
        $FlowRequestResult    = $FlowRequest->rules('add', params: $params, member: $member);
        $params               = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);
        $priceTemplateService = \Hyperf\Support\make(PriceTemplateService::class, [$member]);
        $result               = $priceTemplateService->handleAddAndEdit(params: $params, member: $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 编辑
     * @Name   edit
     * @Author wangfei
     * @date   2023/11/2 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'templates/edit', methods: 'post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $params               = $request->all();
        $member               = $this->request->UserInfo;
        $LibValidation        = \Hyperf\Support\make(LibValidation::class, [$member]);
        $PriceTemplateRequest = \Hyperf\Support\make(PriceTemplateRequest::class);
        $PriceTemplateResult  = $PriceTemplateRequest->rules('edit', params: $params, member: $member);
        $params               = $LibValidation->validate($params, rules: $PriceTemplateResult['rules'], messages: $PriceTemplateResult['messages']);
        $priceTemplateService = \Hyperf\Support\make(PriceTemplateService::class, [$member]);
        $result               = $priceTemplateService->handleAddAndEdit(params: $params, member: $member);

        return $this->response->json($result);
    }


    /**
     * @DOC   : 模板详情
     * @Name  : template
     * @Author: wangfei
     * @date  : 2022-08-20 2022
     * @param Request $request
     * @return Json
     */
    #[RequestMapping(path: 'templates/template', methods: 'post')]
    public function template(RequestInterface $request): ResponseInterface
    {

        $params        = $request->all();
        $member        = $this->request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate($params, rules: [
            'template_id' => ['required', 'integer', Rule::exists('price_template')->where(function ($query) use ($params, $member) {
                $query->where('member_uid', '=', $member['uid'])->where('template_id', '=', $params['template_id'])->select(['template_id']);
            })]
        ], messages: [
            'template_id.exists' => '当前模板不存在',
        ]);

        $PriceTemplateDb = PriceTemplateModel::query()->with(['use', 'check', 'version'])
            ->where('member_uid', '=', $member['uid'])
            ->where('template_id', '=', $params['template_id'])
            ->first();
        if (!empty($PriceTemplateDb)) {
            $PriceTemplateDb = $PriceTemplateDb->toArray();
        }
        $child_uid = [];
        if (Arr::hasArr($PriceTemplateDb, 'use')) {
            $child_uid[] = $PriceTemplateDb['use']['child_uid'];
        }
        if (Arr::hasArr($PriceTemplateDb, 'check')) {
            $child_uid[] = $PriceTemplateDb['check']['child_uid'];
        }
        if (Arr::hasArr($PriceTemplateDb, 'version')) {
            $child_uid = array_merge($child_uid, array_column($PriceTemplateDb['version'], 'child_uid'));
        }
        $child_uid     = array_unique($child_uid);
        $memberChildDb = $this->member_child($child_uid);
        $versionArr    = [];
        if (Arr::hasArr($PriceTemplateDb, 'use')) {
            $child_uid = $PriceTemplateDb['use']['child_uid'];
            if (Arr::hasArr($memberChildDb, $child_uid)) {
                $child['child_uid']              = $memberChildDb[$child_uid]['child_uid'];
                $child['child_name']             = $memberChildDb[$child_uid]['child_name'];
                $child['name']                   = $memberChildDb[$child_uid]['name'];
                $child['head_url']               = $memberChildDb[$child_uid]['head_url'];
                $PriceTemplateDb['use']['child'] = $child;
            }
            $versionArr[] = $PriceTemplateDb['use']['version_id'];
        }

        if (Arr::hasArr($PriceTemplateDb, 'check')) {
            $child_uid = $PriceTemplateDb['check']['child_uid'];
            if (Arr::hasArr($memberChildDb, $child_uid)) {
                $child['child_uid']                = $memberChildDb[$child_uid]['child_uid'];
                $child['child_name']               = $memberChildDb[$child_uid]['child_name'];
                $child['name']                     = $memberChildDb[$child_uid]['name'];
                $child['head_url']                 = $memberChildDb[$child_uid]['head_url'];
                $PriceTemplateDb['check']['child'] = $child;
            }
            $versionArr[] = $PriceTemplateDb['check']['version_id'];
        }
        $edit_version_button = false;
        if (Arr::hasArr($PriceTemplateDb, 'version')) {
            foreach ($PriceTemplateDb['version'] as $key => $version) {
                if (in_array($version['status'], [
                    PriceTemplateVersionModel::STATUS_WAIT,
                    PriceTemplateVersionModel::STATUS_REFUSE,
                    PriceTemplateVersionModel::STATUS_CANCEL
                ])) {
                    $edit_version_button = true;
                }
                if (Arr::hasArr($version, 'child_uid')) {
                    $child_uid = $version['child_uid'];
                    if (Arr::hasArr($memberChildDb, $child_uid)) {
                        $child['child_uid']                        = $memberChildDb[$child_uid]['child_uid'];
                        $child['child_name']                       = $memberChildDb[$child_uid]['child_name'];
                        $child['name']                             = $memberChildDb[$child_uid]['name'];
                        $child['head_url']                         = $memberChildDb[$child_uid]['head_url'];
                        $PriceTemplateDb['version'][$key]['child'] = $child;
                    }
                }
                if (in_array($version['version_id'], $versionArr)) {
                    unset($PriceTemplateDb['version'][$key]);
                }
            }
            $PriceTemplateDb['version'] = Arr::reorder($PriceTemplateDb['version'], 'version_id');
        }
        $PriceTemplateDb['edit_version_button'] = $edit_version_button; // 是否显示版本新增、编辑按钮
        $result['code']                         = 200;
        $result['msg']                          = "查询成功";
        $result['data']                         = $PriceTemplateDb;
        return $this->response->json($result);
    }


    /**
     * @DOC   : 提交审核
     * @Name  : check
     * @Author: wangfei
     * @date  : 2022-08-04 2022
     * @param Request $request
     * @return Json
     */
    #[RequestMapping(path: 'templates/apply', methods: 'post')]
    public function apply(RequestInterface $request): ResponseInterface
    {
        $params            = $request->all();
        $member            = $this->request->UserInfo;
        $LibValidation     = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params            = $LibValidation->validate($params, rules: [
            'template_id' => ['required', 'integer'],
            'version_id'  => ['required', 'integer']
        ]);
        $PriceTemplateData = PriceTemplateModel::query()
            ->with([
                'flow.node.reviewer',
                'version' => function ($query) {
                    $query->where("status", '=', 1);
                }])
            ->where('member_uid', '=', $member['uid'])->where('template_id', '=', $params['template_id'])->first();
        if (empty($PriceTemplateData)) {
            throw new HomeException('错误：未查询到当前模板数据');
        }

        $PriceTemplateData = $PriceTemplateData->toArray();
        if (Arr::hasArr($PriceTemplateData, 'version')) {
            throw new HomeException('错误：存在审核中的版本、禁止提交，若确实需要提交，请撤销原审核');
        }

        $VersionData = PriceTemplateVersionModel::query()->where('version_id', '=', $params['version_id'])
            ->where('template_id', '=', $params['template_id'])->where('member_uid', '=', $member['uid'])->first();
        if (empty($VersionData)) {
            throw new HomeException('错误：未查询到当前模板版本数据');
        }
        $VersionData = $VersionData->toArray();
        if (!in_array($VersionData['status'], [0, 4])) {
            throw new HomeException('错误：当前模板版本为非 待提交审核 状态。');
        }
        $PriceTemplateService = \Hyperf\Support\make(PriceTemplateService::class, [$member]);
        $flowCheckData        = $PriceTemplateService->flowCheckData(member: $member, PriceTemplateData: $PriceTemplateData);
        $next                 = FlowService::handle($PriceTemplateData['flow'])->next();
        $nextCheck            = $next['check'];
        $time                 = time();
        // 保存数据
        Db::beginTransaction();
        try {
            $check_id = Db::table("flow_check")->insertGetId($flowCheckData);

            $add['check_id']       = $check_id;
            $add['flow_id']        = $PriceTemplateData['flow_id'];
            $add['project_cfg_id'] = $PriceTemplateService->project['cfg_id'];
            $add['add_time']       = $time;
            $nextCheck             = Arr::pushArr($add, $nextCheck);
            Db::table("flow_check_item")->insert($nextCheck);

            //原来的审核 排除掉审核数据
            if (Arr::hasArr($VersionData, 'check_id') && $VersionData['check_id'] > 0) {
                Db::table("flow_check_item")->where('check_id', '=', $VersionData['check_id'])->delete();
            }

            Db::table("price_template_version")->where('version_id', '=', $params['version_id'])
                ->update(['check_id' => $check_id, 'status' => 1, 'update_time' => $time]);

            Db::table("price_template")->where('template_id', '=', $params['template_id'])
                ->update(['check_version' => $params['version_id']]);
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '提交成功：请等待审核';
        } catch (\Exception $e) {
            Db::rollback();
            $result['code'] = 201;
            $result['msg']  = '提交失败：' . $e->getMessage();
        }
        return $this->response->json($result);

    }

    /**
     * @DOC   : 撤销申请
     * @Name  : revoke
     * @Author: wangfei
     * @date  : 2022-08-06 2022
     * @param Request $request
     * @return Json
     */
    #[RequestMapping(path: 'templates/revoke', methods: 'post')]
    public function revoke(RequestInterface $request): ResponseInterface
    {
        $params        = $request->all();
        $member        = $this->request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate($params, rules: [
            'template_id' => ['required', 'integer'],
            'version_id'  => ['required', 'integer'],
            'check_id'    => ['required', 'integer']
        ]);


        $data = PriceTemplateVersionModel::query()->with([
            'member'                    => function ($query) {
                $query->select(['uid', 'user_name', 'nick_name']);
            }, 'item', 'check', 'child' => function ($query) {
                $query->select(['child_uid', 'child_name', 'name', 'realname']);
            }])->where('version_id', '=', $params['version_id'])->where('template_id', '=', $params['template_id'])
            ->where('member_uid', '=', $member['uid'])
            ->first();

        if (empty($data)) {
            throw new HomeException('撤销失败：原因->当前需要撤销数据不存在。');
        }
        $data = $data->toArray();
        if ($data['status'] == 3) {
            throw new HomeException('撤销失败：原因->当前审核已由其他人审核完成。');
        }

        if (Arr::hasArr($data, 'child')) {
            if ($data['child']['child_uid'] !== $member['child_uid'] && $member['child_uid'] > 0) {
                throw new HomeException('撤销失败：原因->当前操作只能由版本的创建人 ' . $data['child']['name'] . ' 来撤销');
            }
        }
        if (!Arr::hasArr($data['check'], 'flow')) {
            throw new HomeException('撤销失败：原因->当前流程数据为空');
        }

        Db::beginTransaction();
        try {
            Db::table('price_template_version')
                ->where('template_id', '=', $data['template_id'])
                ->where('version_id', '=', $data['version_id'])->update(['status' => 4]);

            Db::table('flow_check')->where('check_id', '=', $data['check_id'])->update(['status' => 4]);
            Db::table('price_template')->where('template_id', '=', $data['template_id'])->update(['check_version' => 0]);
            Db::table('flow_check_item')
                ->where('check_id', '=', $data['check_id'])
                ->where('check_status', '=', 0)
                ->update(['check_status' => 4]);

            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '撤销成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }


    /**
     * @DOC   : 审核列表
     * @Name  : check
     * @Author: wangfei
     * @date  : 2022-08-05 2022
     * @param Request $request
     * @return Json
     */
    #[RequestMapping(path: 'templates/check', methods: 'post')]
    public function check(RequestInterface $request): ResponseInterface
    {
        $params               = $request->all();
        $member               = $this->request->UserInfo;
        $LibValidation        = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params               = $LibValidation->validate($params, rules: [
            'page'         => ['required', 'integer'],
            'limit'        => ['required', 'integer'],
            'check_status' => ['array'] //审核状态 0：待审核 1：同意 ,2：拒绝，3：其他人已审 4：撤销，多个值，请用逗号隔开。
        ]);
        $PriceTemplateService = \Hyperf\Support\make(PriceTemplateService::class, [$member]);
        $where[]              = ['check_uid', '=', $member['uid']]; //主账号
        $where[]              = ['check_child_uid', '=', $member['child_uid']]; // 子账号
        $where[]              = ['project_cfg_id', '=', $PriceTemplateService->project['cfg_id']];//项目ID
        $check_status         = (Arr::hasArr($params, 'check_status')) ? $params['check_status'] : [0];
        $paginate             = FlowCheckItemModel::query()->with([
            'member' => function ($query) {
                $query->select(['uid', 'user_name', 'nick_name', 'role_id']);
            },
            'child'  => function ($query) {
                $query->select(['child_uid', 'child_name', 'name']);
            }, 'version.template', 'flow'])->where($where)
            ->whereIn('check_status', $check_status)->paginate($params['limit'] ?? 20)->toArray();

        try {
            $version       = array_column($paginate['data'], 'version');
            $child_uid_arr = array_column($version, 'child_uid');
            $child_uid_arr = array_unique($child_uid_arr);
            if (!empty($child_uid_arr)) {
                $childDb = $this->member_child($child_uid_arr);
                foreach ($paginate['data'] as $key => $val) {
                    $version = Arr::hasArr($val, 'version') ? $val['version'] : [];
                    if (Arr::hasArr($childDb, $version['child_uid'])) {
                        $paginate['data'][$key]['version']['child'] = $childDb[$version['child_uid']];
                    }

                }
            }
        } catch (\Exception $e) {

        }
        $result['code'] = 200;
        $result['msg']  = '获取成功';
        $result['data'] = $paginate;

        return $this->response->json($result);
    }


    /**
     * @DOC   : 审核操作：同意
     * @Name  : agreen
     * @Author: wangfei
     * @date  : 2022-04-24 2022
     * @param Request $request
     * @return \think\response\Json
     */
    #[RequestMapping(path: 'templates/agree', methods: 'post')]
    public function agree(RequestInterface $request): ResponseInterface
    {
        $params               = $request->all();
        $member               = $this->request->UserInfo;
        $LibValidation        = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params               = $LibValidation->validate($params, rules: [
            'template_id' => ['required', 'integer'],
            'check_id'    => ['required', 'integer'],
            'item_id'     => ['required', 'integer'],
            'version_id'  => ['required', 'integer'],
            'info'        => ['string']
        ]);
        $PriceTemplateService = \Hyperf\Support\make(PriceTemplateService::class, [$member]);
        $where[]              = ['item_id', '=', $params['item_id']];
        $where[]              = ['check_uid', '=', $member['uid']];
        $where[]              = ['check_child_uid', '=', $member['child_uid']];


        $data = FlowCheckItemModel::query()
            ->with(['check.version',
                    'member' => function ($query) {
                        $query->select(['uid', 'user_name', 'nick_name', 'role_id']);
                    },
                    'child'  => function ($query) {
                        $query->select(['child_uid', 'child_name', 'name']);
                    }])
            ->where($where)->first();

        if (empty($data)) {
            throw new HomeException('数据不存在、或您没权限审核。');
        }
        $data = $data->toArray();
        if (Arr::hasArr($data, 'check_child_uid') && $data['check_child_uid'] !== $member['child_uid']) {
            throw new HomeException('错误：当前审核人应为->' . $data['child']['child_name']);
        }

        if ($data['check_status'] == 3) {
            throw new HomeException('错误：当前审核已由其他人审核完成。');
        }
        if ($data['check_status'] != 0) {
            throw new HomeException('错误：当前审核操作完成。');
        }

        if (!Arr::hasArr($data['check'], 'flow')) {
            throw new HomeException('错误：当前流程数据为空');
        }
        $version                 = $data['check']['version'];
        $flowData                = $data['check']['flow'];
        $prev['check_node_id']   = Arr::hasArr($data, 'check_node_id') ? $data['check_node_id'] : 0;
        $prev['check_uid']       = Arr::hasArr($data, 'check_uid') ? $data['check_uid'] : 0;
        $prev['check_child_uid'] = Arr::hasArr($data, 'check_child_uid') ? $data['check_child_uid'] : 0;
        unset($where);
        $time      = time();
        $nextCheck = $nextNode = [];
        $next      = FlowService::handle($flowData)->member(member: $member)->checkNode($prev['check_node_id'])->next();

        $checkNode            = $next['checkNode'];
        $nextCheck            = $next['check'];
        $nextNode             = $next['node'];
        $flowCheck['node_id'] = 0;
        if (!empty($nextCheck)) {
            $flowCheck['node_id']  = $nextNode['node_id'];
            $add['project_cfg_id'] = $PriceTemplateService->project['cfg_id'];
            $add['flow_id']        = $data['flow_id'];
            $add['check_id']       = $data['check_id'];
            $add['add_time']       = $time;
            $add['check_status']   = 0;
            $nextCheck             = Arr::pushArr($add, $nextCheck);
        }

        $currentCheck['check_id']     = $params['check_id'];
        $currentCheck['item_id']      = $data['item_id'];
        $currentCheck['check_info']   = Arr::hasArr($params, 'info') ? $params['info'] : '同意';
        $currentCheck['check_time']   = $time;
        $currentCheck['check_status'] = 1; //同意

        Db::beginTransaction();
        try {

            Db::table('flow_check')->where('check_id', '=', $currentCheck['check_id'])->update($flowCheck);
            switch ($checkNode['node_status']) {
                case 0: //指定审核
                    if ($next['checkNodeKey'] + 1 < $next['nodeTotal'] && !empty($nextCheck)) {
                        Db::table('flow_check_item')->insert($nextCheck);
                    }
                    break;
                case 1: //会审
                    $check_status_count = Db::table("flow_check_item")
                        ->where('check_id', '=', $currentCheck['check_id'])
                        ->where('check_node_id', '=', $checkNode['node_id'])
                        ->where('check_status', '=', 0)
                        ->count();
                    if ($check_status_count <= 1 && $next['checkNodeKey'] + 1 < $next['nodeTotal'] && !empty($nextCheck)) {
                        Db::table('flow_check_item')->insert($nextCheck);
                    }
                    break;
                case 2: //或审
                    if ($next['checkNodeKey'] + 1 < $next['nodeTotal'] && !empty($nextCheck)) {
                        Db::table('flow_check_item')->insert($nextCheck);
                        //TODO 或审的，修改其他的状态为：3
                    }
                    $checkItemDb = Db::table('flow_check_item')
                        ->select(['item_id', 'check_id', 'check_status'])
                        ->where('check_id', '=', $currentCheck['check_id'])
                        ->where('check_node_id', '=', $checkNode['node_id'])->select()->get();
                    if (!empty($checkItemDb)) {
                        $checkItemDb  = $checkItemDb->toArray();
                        $checkItemArr = [];
                        foreach ($checkItemDb as $k => $v) {
                            if ($v->item_id != $currentCheck['item_id']) {
                                $checkItemArr[] = $v->item_id;
                            }
                        }
                        if (!empty($checkItemArr)) {
                            Db::table('flow_check_item')->whereIn('item_id', $checkItemArr)->update(['check_status' => 3]);
                        }
                    }
                    break;
            }
            Db::table("flow_check_item")->where('item_id', '=', $currentCheck['item_id'])->update($currentCheck);

            if ($next['checkNodeKey'] + 1 >= $next['nodeTotal']) {
                $flowCheck['status'] = 2; //同意  状态：1：审核中 2：同意 ,3：拒绝,4：撤销。
                Db::table('flow_check')
                    ->where('check_id', '=', $currentCheck['check_id'])
                    ->update($flowCheck);
                //价格模板版本
                Db::table('price_template_version')->where('template_id', '=', $version['template_id'])
                    ->where('version_id', '=', $version['version_id'])->update(['status' => 2]);

                Db::table("price_template")->where('template_id', '=', $version['template_id'])
                    ->where('check_version', '=', $version['version_id'])->update(['check_version' => 0]);
            }
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '审核成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['msg'] = $e->getMessage();
            Db::rollback();
        }
        return $this->response->json($result);
    }


    /**
     * @DOC   : 拒绝
     * @Name  : refuse
     * @Author: wangfei
     * @date  : 2022-05-04 2022
     * @param Request $request
     */
    #[RequestMapping(path: 'templates/refuse', methods: 'post')]
    public function refuse(RequestInterface $request): ResponseInterface
    {
        $params        = $request->all();
        $member        = $this->request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate($params, rules: [
            'template_id' => ['required', 'integer'],
            'check_id'    => ['required', 'integer'],
            'item_id'     => ['required', 'integer'],
            'version_id'  => ['required', 'integer'],
            'info'        => ['string']
        ]);
        //   $PriceTemplateService = \Hyperf\Support\make(PriceTemplateService::class,[$member);

        $where[] = ['item_id', '=', $params['item_id']];
        $where[] = ['check_uid', '=', $member['uid']];
        $where[] = ['check_child_uid', '=', $member['child_uid']];

        $data = FlowCheckItemModel::query()
            ->with(['check.version',
                    'member' => function ($query) {
                        $query->select(['uid', 'user_name', 'nick_name', 'role_id']);
                    },
                    'child'  => function ($query) {
                        $query->select(['child_uid', 'child_name', 'name']);
                    }])
            ->where($where)->first();


        if (empty($data)) {
            throw new HomeException('当前审核数据不存在。');
        }
        $data = $data->toArray();
        if (Arr::hasArr($data, 'check_child_uid') && $data['check_child_uid'] !== $member['child_uid']) {
            throw new HomeException('错误：当前审核人应为->' . $data['child']['child_name']);
        }

        if ($data['check_status'] == 3) {
            throw new HomeException('错误：当前审核已由其他人审核完成。');
        }
        if ($data['check_status'] != 0) {
            throw new HomeException('错误：当前审核操作完成。');
        }

        if (!Arr::hasArr($data['check'], 'flow')) {
            throw new HomeException('错误：当前流程数据为空');
        }
        $version                 = $data['check']['version']; //版本信息
        $flowData                = $data['check']['flow'];
        $prev['check_node_id']   = Arr::hasArr($data, 'check_node_id') ? $data['check_node_id'] : 0;
        $prev['check_uid']       = Arr::hasArr($data, 'check_uid') ? $data['check_uid'] : 0;
        $prev['check_child_uid'] = Arr::hasArr($data, 'check_child_uid') ? $data['check_child_uid'] : 0;

        $time                         = time();
        $currentCheck['check_id']     = $params['check_id'];
        $currentCheck['item_id']      = $params['item_id'];
        $currentCheck['check_info']   = Arr::hasArr($params, 'info') ? $params['info'] : "拒绝";
        $currentCheck['check_time']   = $time;
        $currentCheck['check_status'] = 2; //拒绝

        $next      = FlowService::handle($flowData)->member($this->request->UserInfo)->checkNode($prev['check_node_id'])->next();
        $checkNode = $next['checkNode'];
        Db::beginTransaction();
        try {
            Db::table('flow_check')->where('check_id', '=', $currentCheck['check_id'])->update(['status' => 2]);
            Db::table('flow_check_item')->where('check_id', '=', $data['check_id'])
                ->where('item_id', '=', $currentCheck['item_id'])->update($currentCheck);

            switch ($checkNode['node_status']) {
                case 1:
                case 2: //或审
                    $checkItemDb = Db::table('flow_check_item')
                        ->select(['item_id', 'check_id', 'check_status'])
                        ->where('check_id', '=', $currentCheck['check_id'])
                        ->where('check_node_id', '=', $checkNode['node_id'])->select()->get();
                    if (!empty($checkItemDb)) {
                        $checkItemDb  = $checkItemDb->toArray();
                        $checkItemArr = [];
                        foreach ($checkItemDb as $k => $v) {
                            if ($v->item_id != $currentCheck['item_id']) {
                                $checkItemArr[] = $v->item_id;
                            }
                        }
                        if (!empty($checkItemArr)) {
                            Db::table('flow_check_item')->whereIn('item_id', $checkItemArr)
                                ->update(['check_status' => 3]);
                        }
                    }
                    break;
            }

            Db::table('price_template_version')->where('template_id', '=', $version['template_id'])
                ->where('version_id', '=', $version['version_id'])->update(['status' => 3, 'update_time' => $time]);

            //价格模板，拒绝以后，进入历史版本
            Db::table('price_template')->where('template_id', '=', $version['template_id'])->update(['check_version' => 0]);

            // 提交事务
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '操作成功';
        } catch (\Exception $e) {
            // 回滚事务
            $result['code'] = 201;
            $result['msg']  = '操作失败：' . $e->getMessage();
            Db::rollback();
        }

        return $this->response->json($result);
    }


    /**
     * @DOC   : 版本详情
     * @Name  : version
     * @Author: wangfei
     * @date  : 2022-08-20 2022
     * @param Request $request
     * @return Json
     */
    #[RequestMapping(path: 'templates/version', methods: 'post')]
    public function version(RequestInterface $request): ResponseInterface
    {
        $params        = $request->all();
        $member        = $this->request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params        = $LibValidation->validate($params, rules: [
            'template_id' => ['required', 'integer', Rule::exists('price_template')->where(function ($query) use ($params, $member) {
                $query->where('member_uid', '=', $member['parent_agent_uid'])->where('template_id', '=', $params['template_id'])->select(['template_id']);
            })],
            'version_id'  => ['required', 'integer', Rule::exists('price_template_version')->where(function ($query) use ($params, $member) {
                $query->where('version_id', '=', $params['version_id'])
                    ->where('template_id', '=', $params['template_id'])
                    ->where('member_uid', '=', $member['parent_agent_uid']);
            })],
            'info'        => ['string']
        ], messages: [
            'template_id.exists' => '当前模板不存在、请确认',
            'version_id.exists'  => '当前模板不存在对应版本、请确认',
        ]);

        $PriceTemplate = PriceTemplateModel::query()->with(
            ['send', 'target', 'currency',
             'version' => function ($query) use ($params) {
                 $query->with(['item'])->where('version_id', '=', $params['version_id']);
             },
             'member'  => function ($query) {
                 $query->select(['uid', 'user_name']);
             }
            ])->where('template_id', '=', $params['template_id'])->first()->toArray();
        if (Arr::hasArr($PriceTemplate, 'version')) {
            $PriceTemplate['child'] = [];
            $version                = current($PriceTemplate['version']);
            if ($version['child_uid'] > 0) {
                $MemberChildDb          = MemberChildModel::query()->with(['chlidRole' => function ($query) {
                    $query->select(['role_id', 'name']);
                }])->where('child_uid', '=', $version['child_uid'])
                    ->select(['child_name', 'child_uid', 'name', 'realname', 'child_role_id'])->first();
                $PriceTemplate['child'] = $MemberChildDb;
            }
            $PriceTemplate['version'] = $version;
            $FlowCheckDb              = [];
            // $version['check_id'] >0 说明 已提交审核
            if ($version['check_id'] > 0) {
                $FlowService = \Hyperf\Support\make(FlowService::class);
                $FlowCheckDb = $FlowService->handleFlowCheckDb($version['check_id']);
            }
            $PriceTemplate['check'] = $FlowCheckDb;
        }
        $result['code'] = 200;
        $result['msg']  = "查询成功";
        $result['data'] = $PriceTemplate;
        return $this->response->json($result);
    }

    /**
     * @DOC 修改模板基本数据
     * @Name   base
     * @Author wangfei
     * @date   2023/11/2 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'templates/base', methods: 'post')]
    public function base(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $result['msg']  = '修改失败';
        $params         = $request->all();
        $member         = $this->request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params         = $LibValidation->validate($params, rules: [
            'template_id'       => ['required', 'integer', Rule::exists('price_template')->where(function ($query) use ($params, $member) {
                $query->where('member_uid', '=', $member['uid'])->where('template_id', '=', $params['template_id'])->select(['template_id']);
            })],
            'template_name'     => ['required', 'string'],
            'currency_id'       => ['required'],
            'send_country_id'   => ['required'],
            'target_country_id' => ['required'],
            'status'            => ['nullable', Rule::in([1, 0])],
            //            'flow_id'           => ['required']
        ], messages: [
            'template_id.exists' => '当前模板不存在、请确认',

        ]);
        if (PriceTemplateModel::query()->where('member_uid', '=', $member['uid'])
            ->where('template_id', '=', $params['template_id'])
            ->update($params)) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
        }

        return $this->response->json($result);
    }


    /**
     * @DOC   : 切换线衫的价格模板
     * @Name  : change
     * @Author: wangfei
     * @date  : 2022-11-08 2022
     * @param Request $request
     * @return Json
     */
    #[RequestMapping(path: 'templates/change', methods: 'post')]
    public function change(RequestInterface $request): ResponseInterface
    {
        $result['code'] = 201;
        $params         = $request->all();
        $member         = $this->request->UserInfo;
        $LibValidation  = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params         = $LibValidation->validate($params, rules: [
            'template_id' => ['required', 'integer', Rule::exists('price_template')->where(function ($query) use ($params, $member) {
                $query->where('member_uid', '=', $member['uid'])->where('template_id', '=', $params['template_id'])->select(['template_id']);
            })],
            'version_id'  => ['required', 'integer', Rule::exists('price_template_version')->where(function ($query) use ($params, $member) {
                $query->where('version_id', '=', $params['version_id'])
                    ->where('template_id', '=', $params['template_id'])
                    ->where('member_uid', '=', $member['uid']);
            })],
            'info'        => ['string']
        ], messages: [
            'template_id.exists' => '当前模板不存在、请确认',
            'version_id.exists'  => '当前模板不存在对应版本、请确认',
        ]);
        //当前账号uid
        try {
            $versionDb = PriceTemplateVersionModel::query()
                ->where('version_id', '=', $params['version_id'])
                ->where('template_id', '=', $params['template_id'])
                ->where('member_uid', '=', $member['uid'])
                ->first();
            if (empty($versionDb)) {
                throw new HomeException("错误：当前版本不存在");
            }
            $versionDb = $versionDb->toArray();
            if ($versionDb['status'] !== 2) {
                throw new HomeException("当前版本未通过、禁止上线");
            }
            unset($where);
            if (PriceTemplateModel::query()->where('template_id', '=', $params['template_id'])->update(['use_version' => $params['version_id']])) {
                $result['code'] = 200;
                $result['msg']  = "切换成功：新版本为:[" . $versionDb['version_name'] . "]";
            }
        } catch (\Exception $e) {
            $result['msg'] = "错误：" . $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   : 编辑价格模板下的版本
     * @Name  : versionHandle
     * @Author: wangfei
     * @date  : 2022-12-15 2022
     * @param Request $request
     * @return Json
     */
    #[RequestMapping(path: 'templates/version/handle', methods: 'post')]
    public function versionHandle(RequestInterface $request): ResponseInterface
    {
        $params            = $request->all();
        $member            = $this->request->UserInfo;
        $LibValidation     = \Hyperf\Support\make(LibValidation::class, [$member]);
        $FlowRequest       = \Hyperf\Support\make(PriceTemplateRequest::class);
        $FlowRequestResult = $FlowRequest->rules('versionHandle', params: $params, member: $member);
        $params            = $LibValidation->validate($params, rules: $FlowRequestResult['rules'], messages: $FlowRequestResult['messages']);
        $PriceTemplateDb   = PriceTemplateModel::query()->where('member_uid', '=', $member['uid'])->where('template_id', '=', $params['template_id'])->first()->toArray();
        if (empty($PriceTemplateDb)) {
            throw new HomeException("错误：当前模板不存在");
        }
        if (Arr::hasArr($params, 'version_id')) {
            $TemplateVersionDb = PriceTemplateVersionModel::query()->where('template_id', '=', $params['template_id'])
                ->where('version_id', '=', $params['version_id'])->first()->toArray();
            if (empty($TemplateVersionDb)) {
                throw new HomeException("错误：当前版本不存在");
            }
            if (!in_array($TemplateVersionDb['status'], [0, 3, 4])) {
                throw new HomeException("错误：当前版本已提交审核、或审核已通过。");
            }
        }

        $priceTemplateService = \Hyperf\Support\make(PriceTemplateService::class, [$member]);
        $result               = $priceTemplateService->handleAddAndEditVersion(params: $params, member: $member);
        return $this->response->json($result);
    }

    /**
     * @DOC   : 删除版本
     * @Name  : versionDel
     * @Author: wangfei
     * @date  : 2022-12-15 2022
     * @param Request $request
     * @return Json
     */
    #[RequestMapping(path: 'templates/version/del', methods: 'post')]
    public function versionDel(RequestInterface $request): ResponseInterface
    {
        $params            = $request->all();
        $member            = $this->request->UserInfo;
        $LibValidation     = \Hyperf\Support\make(LibValidation::class, [$member]);
        $params            = $LibValidation->validate($params, rules: [
            'template_id' => ['required', 'integer'],
            'version_id'  => ['required', 'integer']
        ]);
        $PriceTemplateData = PriceTemplateModel::query()
            ->with(['version' => function ($query) use ($params) {
                $query->where("version_id", '=', $params['version_id']);
            }])
            ->where('member_uid', '=', $member['uid'])->where('template_id', '=', $params['template_id'])->first();
        if (empty($PriceTemplateData)) {
            throw new HomeException('错误：当前模板不存在');
        }
        $PriceTemplateData = $PriceTemplateData->toArray();
        if (!Arr::hasArr($PriceTemplateData, 'version')) {
            throw new HomeException('错误：当前模板下不存在此版本');
        }
        if (in_array($PriceTemplateData['version'][0]['status'], [1, 2])) {
            throw new HomeException("禁止删除：当前版本已提交审核、或审核已通过。");
        }
        if ($PriceTemplateData['version'][0]['child_uid'] != $member['child_uid']) {
            throw new HomeException("禁止删除：非自己创建的版本");
        }

        Db::beginTransaction();
        try {
            Db::table('price_template_version')
                ->where('member_uid', '=', $member['uid'])->where('version_id', '=', $params['version_id'])->delete();
            Db::table('price_template_item')
                ->where('template_id', '=', $params['template_id'])
                ->where('version_id', '=', $params['version_id'])->delete();
            if ($PriceTemplateData['version'][0]['check_id'] > 0) {
                Db::table('flow_check')->where('member_uid', '=', $member['uid'])
                    ->where('check_id', '=', $PriceTemplateData['version'][0]['check_id'])->delete();
                Db::table('flow_check_item')->where('check_id', '=', $PriceTemplateData['version'][0]['check_id'])->delete();
            }
            Db::commit();
            $result['code'] = 200;
            $result['msg']  = '删除成功';
        } catch (\Exception $e) {
            Db::rollback();
            $result['code'] = 201;
            $result['msg']  = '删除失败：' . $e->getMessage();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 获取子账号信息
     * @Name   member_child
     * @Author wangfei
     * @date   2023/11/1 2023
     * @param array $child_uid
     * @return mixed[]
     */
    protected function member_child(array $child_uid)
    {
        $Db = MemberChildModel::query()->whereIn('child_uid', $child_uid)->select(['child_uid', 'uid', 'child_name', 'name', 'head_url'])->get()->toArray();
        if (!empty($Db)) {
            return array_column($Db, null, 'child_uid');
        }
        return [];
    }

}
