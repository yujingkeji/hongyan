<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Work;

use App\Common\Lib\Str;
use App\Exception\HomeException;
use App\Model\MemberAkModel;
use App\Model\MemberAuthRoleModel;
use App\Model\MemberChildAuthRoleModel;
use App\Model\WorkAuthMenuModel;
use App\Request\LibValidation;
use App\Service\Cache\BaseCacheService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerFactory;


#[Controller(prefix: "/", server: 'httpWork')]
class MemberController
{
    #[Inject]
    protected BaseCacheService $baseCacheService;
    #[Inject]
    protected ResponseInterface $response;


    #[RequestMapping(path: 'member/info', methods: 'post')]
    public function info(RequestInterface $request)
    {
        $member          = $request->UserInfo;
        $result['code']  = 200;
        $result['msg']   = '查询成功';
        $result['data']  = $member;
        $result['menus'] = [];

        // 区分子账号|主账号
        if (!empty($request->UserInfo['child_role_id'])) {
            $work_menus = MemberChildAuthRoleModel::query()->where('role_id', $member['child_role_id'])->value('work_menus');
        } else {
            $work_menus = MemberAuthRoleModel::query()->where('role_id', $member['role_id'])->value('work_menus');
        }
        if (!empty($work_menus)) {
            $result['menus'] = WorkAuthMenuModel::query()->whereIn('menu_id', $work_menus)
                ->whereIn('menu_type', [1, 2])
                ->orderBy('sort', 'DESC')
                ->get()->toArray();
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   : 用户密钥列表、一个帐号只能创建一个密钥
     * @Name  : ak
     * @Author: wangfei
     * @date  : 2025-02 13:45
     * @param RequestInterface $request
     * @return void
     *
     */
    #[RequestMapping(path: 'member/ak', methods: 'post')]
    public function ak(RequestInterface $request)
    {
        $member = $request->UserInfo;
        // 验证并清理 member 数据
        if (!isset($member['uid'], $member['parent_join_uid'], $member['parent_agent_uid'], $member['warehouse_id'], $member['warehouse_name'])) {
            throw new HomeException('Member information is incomplete.');
        }
        $data   = MemberAkModel::query()
            ->where('parent_agent_uid', $member['parent_agent_uid'])
            ->where('parent_join_uid', $member['parent_join_uid'])
            ->where('member_uid', $member['uid'])
            ->where(function ($query) use ($member) {
                if ($member['child_uid'] > 0) {
                    $query->where('child_uid', $member['child_uid']);
                }
            })
            ->get();
        $result = [
            'code' => 200,
            'msg'  => '创建成功',
            'data' => $data,
        ];
        return $this->response->json($result);
    }

    /**
     * @DOC   : 创建密钥
     * @Name  : AkCreate
     * @Author: wangfei
     * @date  : 2025-02 14:18
     * @param RequestInterface $request
     *
     */
    #[RequestMapping(path: 'member/ak/create', methods: 'post')]
    public function AkCreate(RequestInterface $request)
    {
        try {
            // 验证请求参数
            $validator = \Hyperf\Support\make(LibValidation::class);
            $params    = $validator->validate($request->all(), [
                'desc' => ['string', 'nullable'],
            ]);
            $member    = $request->UserInfo;
            // 验证并清理 member 数据
            if (!isset($member['uid'], $member['parent_join_uid'], $member['parent_agent_uid'], $member['warehouse_id'], $member['warehouse_name'])) {
                throw new HomeException('Member information is incomplete.');
            }
            $ak = 'ck-' . Str::generate_random_string(48);
            // 优化 验证ak是否已经存在
            $ak = $this->checkUniqueAk($ak);
            // 插入数据到数据库
            $insertResult = MemberAkModel::query()->insert([
                'ak_key'           => $ak,
                'desc'             => $params['desc'] ?? '',
                'member_uid'       => $member['uid'],
                'child_uid'        => $member['child_uid'],
                'parent_join_uid'  => $member['parent_join_uid'],
                'parent_agent_uid' => $member['parent_agent_uid'],
                'warehouse_id'     => $member['warehouse_id'],
                'warehouse_name'   => $member['warehouse_name'],
                'create_time'      => time()
            ]);
            // 检查插入结果
            if (!$insertResult) {
                throw new HomeException('Failed to insert record into the database.');
            }
            // 构建返回结果
            $result = [
                'code' => 200,
                'msg'  => '创建成功',
                'ak'   => $ak,
            ];
        } catch (\Throwable $e) {
            // 记录详细的错误日志
            $logger = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'error');
            $logger->error('AkCreate[member/ak/create]:' . $e->getMessage());
            // 返回通用的错误信息
            $result = [
                'code' => 201,
                'msg'  => '创建失败，请稍后再试。',
            ];
        }
        return $this->response->json($result);
    }

    /**
     * @DOC   :检测ak是否重复
     * @Name  : checkUniqueAk
     * @Author: wangfei
     * @date  : 2025-02 19:51
     * @param string $ak
     * @return string
     * * @throws \Random\RandomException
     */
    protected function checkUniqueAk(string $ak)
    {
        // 尝试生成唯一 ak 的最大次数
        $maxAttempts = 10;
        $attempt     = 0;
        while ($attempt < $maxAttempts) {
            try {
                if (!MemberAkModel::query()->where('ak_key', $ak)->exists()) {
                    return $ak;
                }
                // 生成新的随机字符串并重新尝试
                $ak = 'ck-' . Str::generate_random_string(48);
                $attempt++;
            } catch (\Exception $e) {
                $logger = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'error');
                $logger->error('AkDelete[checkUniqueAk] Error:' . $e->getMessage());
                // 处理生成随机字符串时可能发生的异常
                throw new HomeException('Failed to generate unique AK: ' . $e->getMessage(), 0, $e);
            }
        }
        // 如果超过最大尝试次数，抛出异常
        throw new HomeException('Failed to generate a unique AK after ' . $maxAttempts . ' attempts.');
    }

    /**
     * @DOC   : 密钥删除
     * @Name  : AkDelete
     * @Author: wangfei
     * @date  : 2025-02 15:59
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     *
     */
    #[RequestMapping(path: 'member/ak/delete', methods: 'post')]
    public function AkDelete(RequestInterface $request)
    {
        try {
            // 验证请求参数
            $validator = \Hyperf\Support\make(LibValidation::class);
            $params    = $validator->validate($request->all(), [
                'ak_id' => ['required', 'integer'],
            ]);
            $member    = $request->UserInfo;
            // 验证并清理 member 数据
            if (!isset($member['uid'], $member['parent_join_uid'], $member['parent_agent_uid'], $member['warehouse_id'], $member['warehouse_name'])) {
                throw new HomeException('Member information is incomplete.');
            }

            // 插入数据到数据库
            $deleteResult = MemberAkModel::query()->where('ak_id', $params['ak_id'])
                ->where('parent_agent_uid', $member['parent_agent_uid'])
                ->where('parent_join_uid', $member['parent_join_uid'])
                ->where('member_uid', $member['uid'])
                ->delete();
            // 检查插入结果
            if (!$deleteResult) {
                throw new HomeException('Failed to insert record into the database.');
            }
            // 构建返回结果
            $result = [
                'code' => 200,
                'msg'  => '删除成功',
            ];
        } catch (\Throwable $e) {
            // 记录详细的错误日志
            $logger = \Hyperf\Support\make(LoggerFactory::class)->get('log', 'error');
            $logger->error('AkDelete[member/ak/delete] Error:' . $e->getMessage());
            // 返回通用的错误信息
            $result = [
                'code' => 201,
                'msg'  => '删除失败，请稍后再试。',
            ];
        }
        return $this->response->json($result);
    }

}
