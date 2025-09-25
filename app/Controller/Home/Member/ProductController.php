<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Controller\Home\AbstractController;
use App\Exception\HomeException;
use App\Model\AgentMemberModel;
use App\Model\JoinMemberProductTemplateModel;
use App\Model\MemberLineModel;
use App\Model\MemberModel;
use App\Model\PriceTemplateItemModel;
use App\Model\PriceTemplateModel;
use App\Model\ProductModel;
use App\Service\Cache\BaseEditUpdateCacheService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;
use App\Request\LibValidation;


#[Controller(prefix: "member/product")]
class ProductController extends AbstractController
{

    #[Inject]
    protected BaseEditUpdateCacheService $baseEditUpdateCacheService;

    /**
     * @DOC 调整状态
     * @Name   handleStatus
     * @Author wangfei
     * @date   2023/10/16 2023
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: "handleStatus", methods: "post")]
    public function handleStatus(RequestInterface $request)
    {
        $member         = $request->UserInfo;
        $params         = $request->all();
        $LibValidation  = \Hyperf\Support\make(LibValidation::class, [$member]);
        $rule           = [
            'pro_id' => ['required', 'integer', Rule::exists('product')->where(function ($query) use ($params, $member) {
                $query->where('pro_id', '=', $params['pro_id'])->where("member_uid", '=', $member['uid']);
            })],
            'status' => ['required', Rule::in([0, 1])]
        ];
        $result['code'] = 201;
        $result['msg']  = '修改失败';
        $params         = $LibValidation->validate(params: $params, rules: $rule);
        if (Db::table("product")->where('pro_id', '=', $params['pro_id'])->update(['status' => $params['status']])) {
            $result['code'] = 200;
            $result['msg']  = '修改成功';
            $this->baseEditUpdateCacheService->ProductCache($member['uid']);
        }
        return $this->response->json($result);
    }

    /**
     * @DOC 新增产品
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'more_day'          => ['required', 'integer'],
                'pro_name'          => ['required', 'min:4'],
                'm_line_id'         => ['required', 'integer'],
                'least_day'         => ['required', 'integer'],
                'strategy_id'       => ['required', 'integer'],
                'price_template_id' => ['required', 'integer'],
            ],
            [
                'pro_name.min'               => '产品名称最少四位',
                'pro_name.required'          => '产品名称必填',
                'm_line_id.integer'          => '线路错误',
                'm_line_id.required'         => '线路必选',
                'more_day.required'          => '最大天数必填',
                'more_day.integer'           => '最大天数必须整数',
                'least_day.required'         => '最少天数必填',
                'least_day.integer'          => '最少天数必须整数',
                'strategy_id.required'       => '产品的策略必选',
                'strategy_id.integer'        => '产品的策略选择错误',
                'price_template_id.required' => '价格模板必选',
                'price_template_id.integer'  => '价格模板选择错误',
            ]
        );

        $memberLineDb = MemberLineModel::where('member_line_id', $param['m_line_id'])
            ->where('uid', $member['uid'])
            ->where('status', '=', 1)->first();
        if (empty($memberLineDb)) {
            throw new HomeException('当前线路不存在/线路未开启');
        }

        $where['pro_name']   = $param['pro_name'];
        $where['member_uid'] = $member['uid'];
        $productDb           = ProductModel::where($where)->first();
        if (!empty($productDb)) {
            throw new HomeException('当前产品已经存在');
        }

        $pro             = $this->getPro($param, $memberLineDb, $member['uid']);
        $pro['add_time'] = time();
        ProductModel::insert($pro);
        (new BaseEditUpdateCacheService())->ProductCache($member['uid']);
        return $this->response->json(['code' => 200, 'msg' => '产品添加成功', 'data' => []]);
    }


    /**
     * @DOC 编辑产品
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'pro_id'            => ['required', 'integer'],
                'more_day'          => ['required', 'integer'],
                'pro_name'          => ['required', 'min:4'],
                'm_line_id'         => ['required', 'integer'],
                'least_day'         => ['required', 'integer'],
                'strategy_id'       => ['required', 'integer'],
                'price_template_id' => ['required', 'integer'],
            ],
            [
                'pro_id.required'            => '产品必选',
                'pro_id.integer'             => '产品选择错误',
                'pro_name.min'               => '产品名称最少四位',
                'pro_name.required'          => '产品名称必填',
                'm_line_id.integer'          => '线路错误',
                'm_line_id.required'         => '线路必选',
                'more_day.required'          => '最大天数必填',
                'more_day.integer'           => '最大天数必须整数',
                'least_day.required'         => '最少天数必填',
                'least_day.integer'          => '最少天数必须整数',
                'strategy_id.required'       => '产品的策略必选',
                'strategy_id.integer'        => '产品的策略选择错误',
                'price_template_id.required' => '价格模板必选',
                'price_template_id.integer'  => '价格模板选择错误',
            ]
        );

        $memberLineDb = MemberLineModel::where('member_line_id', $param['m_line_id'])
            ->where('uid', $member['uid'])
            ->where('status', '=', 1)->first();
        if (empty($memberLineDb)) {
            throw new HomeException('当前线路不存在/线路未开启');
        }
        $memberLineDb = $memberLineDb->toArray();
        //检查产品是否存在
        unset($where);
        $where['pro_name'] = $param['pro_name'];
        $productDb         = ProductModel::where($where)
            ->whereNotIn('pro_id', [$param['pro_id']])->first();
        if (!empty($productDb)) {
            throw new HomeException('产品名称已存在');
        }

        $pro = $this->getPro($param, $memberLineDb, $member['uid']);
        unset($where);
        $where['pro_id']     = $param['pro_id'];
        $where['member_uid'] = $member['uid'];
        if (ProductModel::where($where)->update($pro)) {
            (new BaseEditUpdateCacheService())->ProductCache($member['uid']);
            return $this->response->json(['code' => 200, 'msg' => '产品修改成功', 'data' => []]);
        }
        throw new HomeException('产品修改失败');
    }

    /**
     * @DOC 产品数据
     */
    public function getPro($param, $memberLineDb, $uid): array
    {
        $pro['pro_name']          = $param['pro_name'];
        $pro['price_template_id'] = $param['price_template_id'];
        $pro['line_id']           = $memberLineDb['line_id'];
        $pro['member_line_id']    = $memberLineDb['member_line_id'];
        $pro['member_uid']        = $uid;
        $pro['strategy_id']       = $param['strategy_id'];
        $pro['least_day']         = $param['least_day'];
        $pro['more_day']          = $param['more_day'];
        $pro['info']              = $param['info'];
        $pro['status']            = $param['status'] ?? 0;
        return $pro;
    }

    /**
     * @DOC 产品列表
     */
    #[RequestMapping(path: 'index', methods: 'post')]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $where[] = ['member_uid', '=', $member['parent_agent_uid']];
        if (Arr::hasArr($param, 'line_id')) {
            $where[] = ['line_id', '=', $param['line_id']];
        }
        if (Arr::hasArr($param, 'pro_name')) {
            $where[] = ['pro_name', 'like', '%' . $param['pro_name'] . '%'];
        }
        $data = ProductModel::where($where)
            ->with(['line', 'strategy', 'price_template'])
            ->orderBy('add_time', 'desc')
            ->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '查询成功',
            'data' => [
                'total' => $data->total(),
                'data'  => $data->items()
            ]
        ]);
    }

    /**
     * @DOC 未授权的用户列表
     */
    #[RequestMapping(path: 'not/auth', methods: 'post')]
    public function notAuth(RequestInterface $request): ResponseInterface
    {
        $member = $request->UserInfo;
        if (!in_array($member['role_id'], [1, 2])) throw new HomeException('非平台代理、无权访问此接口');

        $param = $request->all();

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'product_id' => ['required', 'integer'],
            ],
            [
                'product_id.required' => '产品必选',
                'product_id.integer'  => '产品错误',
            ]
        );


        $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
        $where[] = ['auth_member_uid', '=', $member['uid']];
        if (Arr::hasArr($param, 'product_id')) $where[] = ['product_id', '=', $param['product_id']];

        $useMemberDb = JoinMemberProductTemplateModel::where($where)->pluck('use_member_uid');

        $whereMember[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
        $whereMember[] = ['parent_join_uid', '=', 0];
        $whereMember[] = ['agent_status', '=', 2];
        $data          = AgentMemberModel::query();

        if (Arr::hasArr($param, 'nick_name')) {
            $memberWhere['nick_name'] = $param['nick_name'];

            $memberDb = MemberModel::where('nick_name', $param['nick_name'])->first();
            unset($memberWhere);
            if (!empty($memberDb)) {
                $whereMember[] = ['member_uid', '=', $memberDb['uid']];
            } else {
                // 当没查询到数据的时候，可以通过查询一个不存在的数据，返回空结果
                $whereMember[] = ['member_uid', '=', 0]; //查询一个不存在的数据
            }
            $data = $data->where($whereMember);
        } else {
            $data = $data->where($whereMember)->whereNotIn('member_uid', $useMemberDb);
        }

        $data = $data->with(
            [
                'member' => function ($query) {
                    $query->select(['uid', 'user_name', 'role_id', 'nick_name', 'email', 'tel', 'reg_time']);
                }
            ]
        )->paginate($param['limit'] ?? 20);

        return $this->response->json(
            [
                'code' => 200,
                'msg'  => '获取成功',
                'data' => [
                    'total' => $data->total(),
                    'data'  => $data->items(),
                ]
            ]);
    }

    /**
     * @DOC 操作授权
     */
    #[RequestMapping(path: 'auth', methods: 'post')]
    public function auth(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'product_id'  => ['required', 'integer'],
                'member'      => ['required', 'array'],
                'template_id' => ['required', 'integer'],
                'discount'    => ['required', 'numeric'],
            ],
            [
                'product_id.required'  => '缺少产品',
                'member.required'      => '授权用户必选',
                'member.array'         => '授权用户错误',
                'template_id.integer'  => '价格模板错误',
                'template_id.required' => '价格模板必选',
                'discount.numeric'     => '价格错误',
                'discount.required'    => '请填写打折优惠',
            ]
        );

        if (!in_array($member['role_id'], [1, 2])) throw new HomeException('非平台代理、无权访问此接口');

        $ProductDb = ProductModel::where('member_uid', $member['uid'])
            ->where('pro_id', $param['product_id'])->first();
        if (empty($ProductDb)) {
            throw new HomeException("当前产品不存在、无法授权");
        }


        //改产品已授权用户
        $where['product_id']      = $param['product_id'];
        $where['auth_member_uid'] = $member['uid'];
        $setAuthMember            = JoinMemberProductTemplateModel::where($where)->pluck('use_member_uid');
        unset($where);
        $JoinProductMember = [];
        $discount          = Arr::hasArr($param, 'discount') ? $param['discount'] : 0;
        $price_template_id = ($ProductDb['price_template_id'] == $param['template_id']) ? 0 : $param['template_id'];
        $time              = time();
        foreach ($param['member'] as $key => $val) {
            if (!in_array($val, (array)$setAuthMember)) {  //排除已经授权的用户
                $JoinProductMember[$key]['use_member_uid']    = $val;//使用人 加盟商ID，产品为平台代理授权给加盟商
                $JoinProductMember[$key]['parent_agent_uid']  = $member['uid'];//平台UID
                $JoinProductMember[$key]['auth_member_uid']   = $member['uid'];//授权人，一般为平台代理
                $JoinProductMember[$key]['product_id']        = $param['product_id'];
                $JoinProductMember[$key]['line_id']           = $ProductDb['line_id'];
                $JoinProductMember[$key]['price_template_id'] = $price_template_id;//价格模板ID
                $JoinProductMember[$key]['discount']          = $discount;//折扣
                $JoinProductMember[$key]['add_time']          = $time;//添加时间
            }
        }
        if (!empty($JoinProductMember)) {
            JoinMemberProductTemplateModel::insert($JoinProductMember);
            return $this->response->json(['code' => 200, 'msg' => '排除已经授权的用户，其他用户已授权成功', 'data' => []]);
        }
        return $this->response->json(['code' => 200, 'msg' => '排除已经授权的用户，未存在需要授权的用户', 'data' => []]);
    }

    /**
     * @DOC 当前产品授权的加盟商列表
     */
    #[RequestMapping(path: 'member', methods: 'post')]
    public function member(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'product_id' => ['required', 'integer'],
            ],
            [
                'product_id.required' => '产品必选',
                'product_id.integer'  => '产品错误',
            ]
        );

        $where[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
        $where[] = ['auth_member_uid', '=', $member['uid']];
        $where[] = ['product_id', '=', $param['product_id']];

        $data = JoinMemberProductTemplateModel::where($where)
            ->with(
                [
                    'member'         => function ($query) {
                        $query->select(['uid', 'user_name']);
                    },
                    'price_template' => function ($query) {
                        $query->select(['template_id', 'use_version', 'template_name']);
                    },
                    'product.price_template'
                ]
            )->paginate($param['limit'] ?? 20);

        return $this->response->json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => [
                    'total' => $data->total(),
                    'data'  => $data->items(),
                ]
            ]
        );
    }

    /**
     * @DOC 取消授权
     */
    #[RequestMapping(path: 'cancel/auth', methods: 'post')]
    public function cancelAuth(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;

        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'product_id' => ['required', 'integer'],
                'member'     => ['required', 'array'],
            ],
            [
                'product_id.required' => '产品必选',
                'product_id.integer'  => '产品错误',
                'member.required'     => '取消授权用户必选',
                'member.array'        => '取消授权用户错误',
            ]
        );

        if (!in_array($member['role_id'], [1, 2])) throw new HomeException('非平台代理、无权访问此接口');

        //删除授权加盟商 自己设置的下级客户
        $joinMemberDelWhere[] = ['parent_agent_uid', '=', $member['uid']];
        $joinMemberDelWhere[] = ['product_id', '=', $param['product_id']];
        //删除授权加盟商
        $joinDelWhere[] = ['parent_agent_uid', '=', $member['uid']];
        $joinDelWhere[] = ['product_id', '=', $param['product_id']];

        Db::beginTransaction();
        try {
            Db::table('join_member_product_template')
                ->where($joinMemberDelWhere)
                ->whereIn('auth_member_uid', $param['member'])
                ->delete();//删除加盟商下客户
            Db::table('join_member_product_template')
                ->where($joinDelWhere)
                ->whereIn('use_member_uid', $param['member'])
                ->delete();
            Db::commit();
            return $this->response->json(['code' => 200, 'msg' => '取消成功', 'data' => []]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->response->json(['code' => 201, 'msg' => '取消失败' . $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * @DOC 产品【用户产品】
     */
    #[RequestMapping(path: 'have', methods: 'post')]
    public function have(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        if (!in_array($member['role_id'], [1, 2, 3])) {
            throw new HomeException('平台代理、加盟商才能访问此接口');
        }
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'uid' => ['required', 'integer'],
            ],
            [
                'uid.required' => '用户必选',
                'uid.integer'  => '用户错误',
            ]
        );

        $AgentMemberDb = AgentMemberModel::with(['member' => function ($query) {
            $query->select(['uid', 'user_name']);
        }])
            ->where('member_uid', $param['uid'])
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->first();
        if (!$AgentMemberDb) {
            throw new HomeException('未查询到用户信息');
        }
        $AgentMemberDb = $AgentMemberDb->toArray();

        $JoinWhere = $MemberWhere = [];
        switch ($AgentMemberDb['role_id']) {
            case 3: //加盟商
                $JoinWhere[] = ['use_member_uid', '=', $AgentMemberDb['member_uid']];
                $JoinWhere[] = ['auth_member_uid', '=', $member['parent_agent_uid']];
                $JoinWhere[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
                break;
            case 4: //制单客户
            case 5:
                //当前客户，加盟商锁拥有的产品
                $JoinWhere[]   = ['use_member_uid', '=', $AgentMemberDb['parent_join_uid']];
                $JoinWhere[]   = ['auth_member_uid', '=', $member['parent_agent_uid']];
                $JoinWhere[]   = ['parent_agent_uid', '=', $member['parent_agent_uid']];
                $MemberWhere[] = ['use_member_uid', '=', $AgentMemberDb['member_uid']];
                $MemberWhere[] = ['auth_member_uid', '=', $AgentMemberDb['parent_join_uid']];
                $MemberWhere[] = ['parent_agent_uid', '=', $member['parent_agent_uid']];
                break;
        }

        $queryJoinProduct = JoinMemberProductTemplateModel::with(['product.price_template', 'price_template'])
            ->where($JoinWhere)->get()->toArray();

        $queryJoinMemberProduct = JoinMemberProductTemplateModel::with(['product.price_template', 'price_template'])
            ->where($MemberWhere)->get()->toArray();
        $queryJoinMemberProduct = array_column($queryJoinMemberProduct, null, 'product_id');

        $joinProduct = $data = [];
        foreach ($queryJoinProduct as $key => $val) {
            $product                                 = $val['product'];
            $product_id                              = $product['pro_id'];
            $joinProduct['product_template_item_id'] = $val['product_template_item_id'];
            $joinProduct['add_time']                 = $val['add_time'];
            $joinProduct['product_id']               = $product_id;
            $joinProduct['product_name']             = $product['pro_name'];
            $joinProduct['line_id']                  = $product['line_id'];
            $platform['priceTemplate']               = $product['price_template'];//官方价格模板

            $joinProduct['platform'] = $platform;
            $join['discount']        = $val['discount'];
            $join['priceTemplate']   = Arr::hasArr($val, 'price_template') ? $val['price_template'] : $product['price_template'];
            $joinProduct['join']     = $join; //加盟商价格模板
            //会员模板
            $member = [];
            if (Arr::hasArr($queryJoinMemberProduct, $product_id)) {
                $mProduct                = $queryJoinMemberProduct[$product_id];
                $member['discount']      = $mProduct['discount'];
                $member['priceTemplate'] = Arr::hasArr($mProduct, 'price_template') ? $mProduct['price_template'] : [];
            }
            $joinProduct['member'] = $member;
            array_push($data, $joinProduct);
        }
        return $this->response->json(['code' => 200, 'msg' => '获取成功', 'data' => $data]);


    }

    /**
     * @DOC 编辑 【用户产品】
     */
    #[RequestMapping(path: 'haveEdit', methods: 'post')]
    public function haveEdit(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'item_id'        => ['required', 'integer'],
                'template_id'    => ['integer'],
                'discount'       => ['numeric'],
                'use_member_uid' => ['required', 'integer'],
            ],
            [
                'item_id.required'        => '产品必选',
                'item_id.integer'         => '产品必选',
                'template_id.integer'     => '模板错误',
                'discount.numeric'        => '折扣错误',
                'use_member_uid.required' => '用户错误',
                'use_member_uid.integer'  => '用户错误',
            ]
        );
        $JoinProductDb = JoinMemberProductTemplateModel::where('product_template_item_id', $param['item_id'])
            ->with(['product.price_template', 'price_template'])->first();
        if (!$JoinProductDb) {
            throw new HomeException('当前产品信息不存在、请确认后操作');
        }
        $JoinProductDb   = $JoinProductDb->toArray();
        $priceTemplateDb = PriceTemplateModel::where('template_id', $param['template_id'])
            ->whereIn('member_uid', [$member['uid'], $member['parent_agent_uid']])->first();
        if (!$priceTemplateDb) {
            throw new HomeException('当前价格模板不能在、或模板不属于当前用户、禁止修改');
        }
        $priceTemplateDb = $priceTemplateDb->toArray();


        $data             = [];
        $data['discount'] = $param['discount'];
        $template_id      = $priceTemplateDb['template_id'];
        $product          = $JoinProductDb['product'];
        if (Arr::hasArr($product, 'price_template')) {
            $platform_template_id = $product['price_template']['template_id'];
            if (!Arr::hasArr($param, 'template_id') || $platform_template_id == $param['template_id']) {
                $template_id = 0;
            }
        }


        switch ($member['role_id']) {
            case 1: //平台代理
            case 2: //货运代理
                if ($JoinProductDb['auth_member_uid'] != $member['uid']) {
                    throw new HomeException('当前加盟商客户非您直属加盟商或者非加盟商账号');
                }
                $data['price_template_id'] = $template_id;
                if (JoinMemberProductTemplateModel::where('product_template_item_id', $JoinProductDb['product_template_item_id'])->update($data)) {
                    return $this->response->json(['code' => 200, 'msg' => '设置成功', 'data' => []]);
                }
                return $this->response->json(['code' => 201, 'msg' => '设置失败', 'data' => []]);
            case 3:
                unset($where);
                $JoinMemberWhere['use_member_uid'] = $param['use_member_uid'];
                $JoinMemberWhere['product_id']     = $JoinProductDb['product_id'];

                $JoinMemberPriceTemplateDb = JoinMemberProductTemplateModel::where($JoinMemberWhere)
                    ->with(['product.price_template', 'price_template'])->first();
                //新增：
                $data['price_template_id'] = $template_id;
                if (empty($JoinMemberPriceTemplateDb)) {
                    $data['use_member_uid']    = $param['use_member_uid'];
                    $data['auth_member_uid']   = $request->UserInfo['uid'];
                    $data['parent_agent_uid']  = $request->UserInfo['parent_agent_uid'];
                    $data['product_id']        = $JoinProductDb['product_id'];
                    $data['line_id']           = $JoinProductDb['line_id'];
                    $data['add_time']          = time();
                    if (JoinMemberProductTemplateModel::insert($data)) {
                        return $this->response->json(['code' => 200, 'msg' => '设置成功', 'data' => []]);
                    }
                } else {
                    if (JoinMemberProductTemplateModel::where('product_template_item_id', $JoinMemberPriceTemplateDb['product_template_item_id'])->update($data)) {
                        return $this->response->json(['code' => 200, 'msg' => '设置成功', 'data' => []]);
                    }
                }
                return $this->response->json(['code' => 201, 'msg' => '设置失败', 'data' => []]);
            default:
                return $this->response->json(['code' => 201, 'msg' => '仅平台代理、加盟商能访问此设置', 'data' => []]);
        }


    }


    /**
     * @DOC 用户产品
     */
    #[RequestMapping(path: 'user', methods: 'get,post')]
    public function userLineProductCahe(RequestInterface $request)
    {
        $param = $request->all();
        $data  = [];
        switch ($request->UserInfo['role_id']) {
            case 1:
            case 2:
            case 3:
                throw new HomeException("非商户禁止访问");
                break;
            case 4:
            case 5:
                $joinWhere['use_member_uid']  = $request->UserInfo['parent_join_uid'];
                $joinWhere['auth_member_uid'] = $request->UserInfo['parent_agent_uid'];
                $joinWhere['line_id']         = $param['line_id'];
                $data                         = JoinMemberProductTemplateModel::query()
                    ->with(['product'])
                    ->where($joinWhere)->select()->get()->toArray();
                break;
        }
        $result['code'] = 200;
        $result['data'] = $data;
        return $this->response->json($result);
    }

}
