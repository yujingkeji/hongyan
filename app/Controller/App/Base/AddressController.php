<?php

namespace App\Controller\App\Base;

use App\Controller\Home\HomeBaseController;
use App\Exception\HomeException;
use App\Model\MemberAddressModel;
use App\Request\LibValidation;
use App\Service\AddressService;
use App\Service\ConfigService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: 'app/base/address')]
class AddressController extends HomeBaseController
{
    /**
     * @DOC 小程序地址列表
     */
    #[RequestMapping(path: '', methods: 'get,post')]
    public function index(RequestInterface $request)
    {
        $params        = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $param         = $LibValidation->validate($params,
            [
                'address_type' => ['nullable', 'integer',],
            ], [
                'address_type.integer' => '地址类型必须为整数',
            ]);

        $result = \Hyperf\Support\make(AddressService::class)->getAddress($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 小程序地址详情
     */
    #[RequestMapping(path: 'detail', methods: 'get,post')]
    public function detail(RequestInterface $request)
    {
        $params        = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class);

        $param  = $LibValidation->validate($params,
            [
                'id' => ['required', 'integer',],
            ], [
                'id.required' => '地址id不能为空',
                'id.integer'  => '地址id必须为整数',
            ]);
        $result = \Hyperf\Support\make(AddressService::class)->getAddressDetail($param, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 小程序地址新增
     */
    #[RequestMapping(path: 'add', methods: 'post')]
    public function add(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(AddressService::class)->addAddress($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 小程序地址编辑
     */
    #[RequestMapping(path: 'edit', methods: 'post')]
    public function edit(RequestInterface $request)
    {
        $params = $request->all();
        $member = $request->UserInfo;
        $result = \Hyperf\Support\make(AddressService::class)->editAddress($params, $member);
        return $this->response->json($result);
    }

    /**
     * @DOC 地址删除
     */
    #[RequestMapping(path: 'del', methods: 'post')]
    public function del(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $member        = $request->UserInfo;
        $LibValidation = \Hyperf\Support\make(LibValidation::class, [$member]);
        $LibValidation->validate($param,
            [
                'address_id' => ['required', 'integer']
            ], [
                'address_id.required' => '地址必选',
                'address_id.integer'  => '地址错误'
            ]);
        $where['address_id'] = $param['address_id'];
        $where['member_uid'] = $member['uid'];
        $MemberAddressDb     = MemberAddressModel::where($where)->first();
        if (empty($MemberAddressDb)) {
            throw new HomeException('错误：当前数据不存在');
        }
        if (MemberAddressModel::where($where)->delete()) {
            return $this->response->json(['code' => 200, 'msg' => '删除成功', 'data' => []]);
        }
        return $this->response->json(['code' => 201, 'msg' => '删除失败', 'data' => []]);
    }

    /**
     * @DOC 地址智能解析
     */
    #[RequestMapping(path: 'analysis', methods: 'post')]
    public function analysis(RequestInterface $request): ResponseInterface
    {
        $param         = $request->all();
        $LibValidation = \Hyperf\Support\make(LibValidation::class);
        $LibValidation->validate($param,
            [
                'address' => ['required', 'string']
            ], [
                'address.required' => '未检测到地址信息',
            ]);

        $result = \Hyperf\Support\make(ConfigService::class)->analysis($param['address']);
        return $this->response->json($result);
    }

    /**
     * @DOC 设置默认地址
     */
    #[RequestMapping(path: 'default', methods: 'post')]
    public function default(RequestInterface $request): ResponseInterface
    {
        $param  = $request->all();
        $member = $request->UserInfo;
        $result = (new AddressService())->default($param, $member);
        return $this->response->json($result);
    }


}
