<?php

namespace App\Common\Lib;


use App\Exception\HomeException;
use Hyperf\DbConnection\Db;

/**
 * 日志处理
 * Class AdminLog
 * @package app\admin\sev
 */
class LogLib
{
    protected static string $table        = '';//写入的日志表
    protected string        $target_table = '';//被操作的目标表
    protected array         $log_data     = [];//被操作的目标表内容
    protected array         $where        = [];//日志查询条件
    protected array         $filed        = ['*'];//设置查询的字段
    // 表名=>code
    protected array $code =
        [
            'flow'                => 1001,//流程管理
            'member_credit_apply' => 1002,//授信申请
            'agent_member'        => 1003,//平台代理下级客户关系表
        ];

    /**
     * @DOC   : 设置日志写入表
     * @Name  : table
     * @date  : 2022-04-10 2022
     * @param string $table 日志内容写入表
     * @param string $target_table 被操作的目标表
     * @return Log
     */
    public static function table(string $table)
    {
        self::$table = $table;
        return new self();
    }

    /**
     * @DOC   : 被操作的目标表
     * @Name  : targetTable
     * @date  : 2022-04-10 2022
     * @param string $target_table
     * @return $this
     * @throws Exception
     */
    public function targetTable(string $target_table)
    {
        if (empty($target_table)) throw new HomeException('被操作的表不能为空', 201);
        $this->log_data['target_table']      = $target_table;
        $this->log_data['target_table_code'] = $this->code[$target_table];
        return $this;
    }

    /**
     * @DOC   : 备操作表的ID
     * @Name  : targetTableId
     * @date  : 2022-04-10 2022
     * @param int $vid
     * @throws Exception
     */
    public function targetTableId(int $vid)
    {
        if ($vid == 0) throw new HomeException('table_id 不能为0', 201);
        $this->log_data['table_id'] = $vid;
        return $this;
    }

    /**
     * @DOC   :日志内容
     * @Name  : logInfo
     * @date  : 2022-04-10 2022
     * @param string $msg
     * @return $this
     */
    public function logInfo(string $msg)
    {
        $this->log_data['log_info'] = $msg;
        return $this;
    }

    /**
     * @DOC   : 设置值、添加到日志表
     * @Name  : data
     * @date  : 2022-04-10 2022
     * @param string $data
     * @param string $value
     * @return $this
     */
    public function data($data, string $value = '')
    {
        if (is_array($data)) {
            $this->log_data = array_merge($this->log_data, $data);
        } else {
            if (!empty($data) && empty($value)) {
                $this->log_data[$data] = $value;
            }
        }
        return $this;
    }

    /**
     * @DOC   : 查询条件
     * @Name  : where
     * @date  : 2022-04-11 2022
     * @param array $where
     * @return $this
     */
    public function where(array $where)
    {
        $this->where = array_merge($this->where, $where);
        return $this;
    }

    /**
     * @DOC   :
     * @Name  : filed
     * @date  : 2022-04-11 2022
     * @param string $filed
     * @return $this
     */
    public function filed(string $filed)
    {
        $this->filed = $filed;
        return $this;
    }


    /**
     * @DOC   : 查询日志记录
     * @Name  : select
     * @date  : 2022-04-11 2022
     */
    public function select()
    {
        return Db::table(self::$table)->select($this->filed)->where($this->where)->paginate(15)->toArray();
    }

    /**
     * @DOC   : 写入到日志表
     * @Name  : write
     * @date  : 2022-04-10 2022
     * @param string $msg
     */
    public function write(string $msg = '')
    {
        if (!empty($msg)) {
            $this->log_data['log_info'] = $msg;
        }
        Db::table(self::$table)->insert($this->log_data);
    }
}
