<?php
/**
 * @Tool: PhpStorm.
 * @Company:
 * @Author: zml
 * @Since: 2016/11/20 16:15
 * @Description: 描述
 */

namespace User\Model;


use Think\Model;

class AdminModel extends Model
{
    protected $tableName = 'admin';

    /**
     * @param $uid
     * @param $password
     * @return bool
     * @Author: ludezh
     */
    public function update($uid, $password){
        $where['uid'] = $uid;
        $data['password'] = $password;
        return $this->where($where)->save($data);
    }
    /**
     * @param $uid
     * @return mixed
     * @Author: ludezh
     */
    public function findRowByUid($uid){
        $where['uid'] = $uid;
        return $this->where($where)->find();
    }
    /**
     * 根据条件(手机号/员工编号)登录
     * @param $where
     * @return mixed
     */
    public function login($where){
        return $this->where($where)->find();
    }

    /**
     * 根据uid获取自增id
     * @param $uid
     * @return mixed
     */
    public function getIdByUid($uid){
        $where['uid'] = $uid;
        return $this->where($where)->getField('id');
    }

    /**
     * 根据uid获取真实姓名
     * @param $uid
     * @return mixed
     */
    public function getNameByUid($uid){
        $where['uid'] = $uid;
        return $this->where($where)->getField('real_name');
    }
}