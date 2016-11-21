<?php
/**
 * @Tool: PhpStorm.
 * @Company:
 * @Author: zml
 * @Since: 2016/11/20 13:46
 * @Description: 描述
 */

namespace Staff\Controller;


use Common\Controller\CommonController;

class InspectController extends CommonController
{
    protected $inspectModel;
    protected $subUserModel;
    protected $userModel;
    protected $carMessage;

    public function __construct()
    {
        $this->inspectModel = D('Settle/Inspect');
        $this->subUserModel = D('User/SubUser');
        $this->userModel = D('User/User');
        $this->carMessage = D('Car/Message');
    }

    /**
     *完善勘察信息(勘察回来填写信息)
     */
    public function updateInspect(){
        $carUid = I('post.carUid');        //车主Uid
        $plateNum = I('post.plateNum');    //车牌号
        $inspectSn = I('post.inspectSn');
        $status = 3;
        $headList = session('headList');
        $bodyList = session('bodyList');
        $footerList = session('footerList');
        $otherList = session('otherList');
        $baseImg = session('baseImg');      //登记表基本信息(含车主确认签名)
        $remark = I('post.remark');
        $endTime = date('Y-m-d H:i:s');
        $rs = $this->inspectModel->completeUpdate($inspectSn,$status,$headList,$bodyList,$footerList,$otherList,$baseImg,$remark,$endTime);
        if($rs){
            $userInfo = $this->userModel->getRowByUid($carUid);
            $content = '尊敬的'.$userInfo['username'].'车主,你好!'.$plateNum.'理赔信息已经登记入系统,正在拼命审核中,预计1-3个工作日';
            $data = array($content,60);
            $tempId = '1';
            sendTemplateSMS($userInfo['phone'],$data,$tempId);
            $this->success('提 交 成 功');
            exit;
        }else{
            $this->error('提交失败');
            exit;
        }
    }

    /**
     *获取获取某一个理赔登记详情
     */
    public function oneInspect()
    {
        $inspectSn = I('post.inspectSn');
        $inspectInfo = $this->inspectModel->getRowByInspectSn($inspectSn);
        $inspectArray = array();
        $userInfo = array();
        $headerList = array();
        $bodyList = array();
        $footerList = array();
        $otherList = array();
        foreach($inspectInfo as $k => $v){
            $userInfo = $this->userModel->getRowByUid($v['uid']);
            $carInfo = $this->carMessage->getRowByCarSn($v['car_sn']);
            $inspectArray['inspect_sn'] = $v['inspect_sn'];
            $inspectArray['happen_time'] = $v['happen_time'];
            $inspectArray['address'] = $v['address'];
            $headerList = explode(',',$v['header_img_list']);
            $bodyList = explode(',',$v['body_img_list']);
            $footerList = explode(',',$v['footer_img_list']);
            $otherList = explode(',',$v['other_img_list']);
            $inspectArray['base_img'] = $v['base_img'];
            $inspectArray['start_time'] = $v['start_time'];
            $inspectArray['end_time'] = $v['end_time'];
        }
        $this->assign('headerList',$headerList);
        $this->assign('bodyList',$bodyList);
        $this->assign('footerList',$footerList);
        $this->assign('otherList',$otherList);
        $this->assign('userInfo',$userInfo);
        $this->assign('carInfo',$carInfo);
        $this->assign('inspectInfo',$inspectArray);
    }

    /**
     *勘察登记表状态(接单触发)
     */
    public function changeStatus(){
        $uid = session('uid');
        $inspectSn = I('post.inspectSn');
        $status = 2;
        $date = date('Y-m-d H:i:s');
        $rs = $this->inspectModel->changeStatus($uid,$inspectSn,$status,$date);
        if($rs){
            $carUid = I('post.carUid');
            $carUserInfo = $this->userModel->getRowByUid($carUid);
            $content = '尊敬的'.$carUserInfo['real_name'].'客户,员工编号:'.$uid.'正在插眼传送中,预计15分钟内到达地点';
            $data = array($content,15);
            $tempId = '1';
            sendTemplateSMS($carUserInfo['phone'],$data,$tempId);
            $this->success('接单成功,正在短信通知车主');
            exit;
        }else{
            $this->error('接单失败');
            exit;
        }
    }

    /**
     *根据不同状态和勘察人的uid获取勘察登记列表分页
     */
    public function inspectList()
    {
        $status = I('get.status');
        $uid = session('uid');
        if(empty($status)){
            $status = 1;
        }
        $inspectList = $this->inspectModel->getInspectList($status,$uid);
        $this->assign('inspectList',$inspectList);
        $this->display();
    }

    /**
     *客服点击立即调度(短信通知调度人员/报案车主)
     */
    public function sendInsect(){
        $phone = I('post.phone');       //勘察人员手机号
        $uid = I('post.uid');           //勘察人员的uid
        $userInfo = $this->subUserModel->getRowByUidPhone($uid,$phone);
        if($userInfo['phone']){
            $inspectSn = session('newInspectSn');
            $res = $this->inspectModel->bindInspectUid($uid,$inspectSn);
            if(!$res){
                $this->error('绑定勘察人员失效,建议电话联系');
                exit;
            }
            $str = '员工编号:'.$userInfo['uid'].',你好!系统通知:你有新的出勤订单,订单编号：'.$inspectSn.',请立即登录系统进行核对出勤';
            $data = array($str,10);
            $tempId = "1";
            $rs = sendTemplateSMS($phone,$data,$tempId);
            if ($rs) {
                $carUid = session('carUid');
                $carUserInfo = $this->userModel->getRowByUid($carUid);
                $str1 = '尊敬的'.$carUserInfo['real_name'].'客户,系统正在拼命通知员工编号:'.$userInfo['uid'].'为您处理，请耐心等待';
                $data1 = array($str1,10);
                $tempId1 = "1";
                $result = sendTemplateSMS($carUserInfo['phone'],$data1,$tempId1);
                if($result){
                    session('newInspectSn',null);
                    session('carUid',null);
                }
                $this->success('短 信 正 在 通 知...');
                exit();
            } else {
                $this->error('短信服务跑偏了,建议电话联系车主');
                exit();
            }
        }else{
            $this->error('该勘察人员已经不存在');
            exit;
        }

    }

    /**
     *获取在线可调度人员列表
     */
    public function getYardList(){
        $address =  I('post.address');
        $userList = $this->subUserModel->getUserListBy(1,2,1,$address);
        $this->assign('userList',$userList);
        $this->display();
    }

    /**
     *新增勘察信息(客服人员)
     */
    public function addInspect(){
        $carUid = I('post.uid');
        $carSn = I('post.carSn');
        $policySn = I('post.policySn');
        $typeSn = I('post.typeSn');
        $address = I('post.address');
        $remark = I('post.remark');
        $customUid = session('uid');
        $happenTime = date('Y-m-d H:i:s');
        $rs = $this->inspectModel->addRow($carUid,$carSn,$policySn,$typeSn,$address,$remark,$customUid,$happenTime);
        if($rs){
            $inspectSn = makeEveryNumber('IS',$rs);
            session('newInspectSn',$inspectSn);
            session('carUid',$carUid);
            $this->inspectModel->addInspectSnById($rs,$inspectSn);
            $this->success('信息调度信息成功');
            exit;
        }else{
            $this->error('新增调度信息失败');
            exit;
        }
    }

}