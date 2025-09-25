# Introduction

This is a skeleton application using the Hyperf framework. This application is meant to be used as a starting place for those looking to get their feet wet with Hyperf Framework.

# Requirements

Hyperf has some requirements for the system environment, it can only run under Linux and Mac environment, but due to the development of Docker virtualization technology, Docker for Windows can also be used as the running environment under Windows.

The various versions of Dockerfile have been prepared for you in the [hyperf/hyperf-docker](https://github.com/hyperf/hyperf-docker) project, or directly based on the already built [hyperf/hyperf](https://hub.docker.com/r/hyperf/hyperf) Image to run.

When you don't want to use Docker as the basis for your running environment, you need to make sure that your operating environment meets the following requirements:  

 - PHP >= 8.0
 - Any of the following network engines
   - Swoole PHP extension >= 4.5，with `swoole.use_shortname` set to `Off` in your `php.ini`
   - Swow PHP extension (Beta)
 - JSON PHP extension
 - Pcntl PHP extension
 - OpenSSL PHP extension （If you need to use the HTTPS）
 - PDO PHP extension （If you need to use the MySQL Client）
 - Redis PHP extension （If you need to use the Redis Client）
 - Protobuf PHP extension （If you need to use the gRPC Server or Client）

# Installation using Composer

The easiest way to create a new Hyperf project is to use [Composer](https://getcomposer.org/). If you don't have it already installed, then please install as per [the documentation](https://getcomposer.org/download/).

To create your new Hyperf project:

```bash
$ composer create-project hyperf/hyperf-skeleton path/to/install
```

Once installed, you can run the server immediately using the command below.

```bash
$ cd path/to/install
$ php bin/hyperf.php start
```

This will start the cli-server on port `9501`, and bind it to all network interfaces. You can then visit the site at `http://localhost:9501/`

which will bring up Hyperf default home page.


重构订单转包裹计算逻辑

- 优化了单个订单计算逻辑，支持优惠券使用
- 重构了批量订单计算方法，提高了代码可读性和维护性
- 优化了数据库查询和数据处理逻辑，提高了系统性能
- 修复了一些计算相关的潜在bug，提升了系统稳定性

商品列表:
- 添加参数验证，提高数据安全性
- 优化分类 ID 判断逻辑，提高查询准确性

包裹重量计算:
   - 修复未付费订单明细重新计算逻辑
     -增加订单数据存在性检查
   - 优化订单成本计算结果结构
