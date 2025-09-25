<?php

namespace App\Controller\Home\Member;

use App\Common\Lib\Arr;
use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\MemberPortModel;
use App\Model\PortModel;
use App\Request\LibValidation;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Rule;
use Psr\Http\Message\ResponseInterface;


#[Controller(prefix: "member/port")]
class PortController extends HomeBaseController
{
    /**
     * @DOC 口岸列表，查询接口
     */
    #[RequestMapping(path: "index", methods: "post")]
    public function index(RequestInterface $request): ResponseInterface
    {
        $param   = $request->all();
        $member  = $request->UserInfo;
        $where[] = ['member_uid', '=', $member['uid']];
        if (Arr::hasArr($param, 'country_id')) {
            $where[] = ['country_id', '=', $param['country_id']];
        }
        if (Arr::hasArr($param, 'status', true)) {
            $where[] = ['status', '=', $param['status']];
        }
        if (Arr::hasArr($param, 'port_name')) {
            $where[] = ['port_name', 'like', '%' . $param['port_name'] . '%'];
        }

        $list = MemberPortModel::where($where)
            ->with([
                'country' => function ($query) {
                    $query->select(['country_id', 'country_name', 'country_code']);
                },
                'port'    => function ($query) {
                    $query->select(['port_id', 'name', 'airport', 'railwayport', 'highwayport', 'waterport', 'min_rate']);
                }])
            ->orderBy('add_time', 'desc')
            ->paginate($param['limit'] ?? 20);

        return $this->response->json([
            'code' => 200,
            'msg'  => '获取成功',
            'data' => [
                'total' => $list->total(),
                'data'  => $list->items(),
            ]
        ]);
    }

    /**
     * @DOC 口岸 ：修改状态
     */
    #[RequestMapping(path: "handleStatus", methods: "post")]
    public function handleStatus(RequestInterface $request): ResponseInterface
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($request->all(), [
            'member_port_id' => ['required', 'numeric'],
            'status'         => ['required', 'numeric', Rule::in([1, 0])],
        ], [
            'member_port_id.required' => '请选择要修改的口岸',
            'member_port_id.numeric'  => '请选择要修改的口岸',
            'status.required'         => '缺少修改状态',
            'status.in'               => '状态存储失败',
            'status.numeric'          => '状态存储失败'
        ]);
        $param = $request->all();

        $where['member_port_id'] = $param['member_port_id'];
        $where['member_uid']     = $request->UserInfo['uid'];
        $MemberPort              = MemberPortModel::where($where)->first();
        if (empty($MemberPort)) {
            throw new HomeException('禁止修改：数据不存在');
        }
        if (MemberPortModel::where($where)->update(['status' => $param['status']])) {
            return $this->response->json(['code' => 200, 'msg' => '修改成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '修改失败', 'data' => []]);
    }

    /**
     * @DOC 口岸 : 添加口岸
     */
    #[RequestMapping(path: "handleAdd", methods: "post")]
    public function handleAdd(RequestInterface $request): ResponseInterface
    {
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($request->all(), [
            'port_id' => ['required', 'numeric', 'min:1'],
            'status'  => ['required', 'numeric', Rule::in([1, 0])],
        ], [
            'port_id.required' => '请选择要新增的口岸',
            'port_id.min'      => '请选择要新增的口岸',
            'port_id.numeric'  => '请选择要新增的口岸',
            'status.required'  => '缺少口岸状态',
            'status.in'        => '状态存储失败',
            'status.numeric'   => '状态存储失败'
        ]);
        $param      = $request->all();
        $where[]    = ['port_id', '=', $param['port_id']];
        $SinglePort = PortModel::where($where)->first();
        if (!$SinglePort) {
            throw new HomeException('未查询到口岸信息');
        }
        $SinglePort = $SinglePort->toArray();
        $where[]    = ['member_uid', '=', $request->UserInfo['uid']];
        $MemberPort = MemberPortModel::where($where)->first();
        if (!empty($MemberPort)) {
            throw new HomeException('请勿重复添加口岸');
        }

        $time               = time();
        $port['port_id']    = $param['port_id'];
        $port['country_id'] = $SinglePort['country_id'];
        $port['port_name']  = $SinglePort['name'];
        $port['member_uid'] = $request->UserInfo['uid'];
        $port['status']     = $param['status'] ?? 1;
        $port['add_time']   = $time;

        $member_port_true = MemberPortModel::insert($port);
        if ($member_port_true) {
            return $this->response->json(['code' => 200, 'msg' => '新增成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '新增失败', 'data' => []]);
    }

}
