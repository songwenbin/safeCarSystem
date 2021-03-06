<?php
/**
 * @Tool: PhpStorm.
 * @Company:
 * @Author: zml
 * @Since: 2016/11/21 11:09
 * @Description: 描述用于理赔报价
 */

namespace Staff\Controller;


use Common\Controller\CommonController;

class RecordController extends CommonController
{
    protected $inspectModel;
    protected $userModel;
    protected $subUserModel;
    protected $recordModel;
    protected $carMessageModel;
    protected $policyTypeModel;
    protected $payModel;
    protected $bankPayModel;

    public function __construct()
    {
    	parent::__construct();
    	layout(true);
        $this->userModel = D('User/User');
        $this->subUserModel = D('User/SubUser');
        $this->carMessageModel = D('Car/Message');
        $this->recordModel = D('Settle/Record');
        $this->inspectModel = D('Settle/Inspect');
        $this->policyTypeModel = D('Settle/PolicyType');
        $this->payModel = D('Pay/Pay');
        $this->bankPayModel = D('Pay/BankPay');
    }

    public function paying(){
        $uid = session('uid');
//        print_r($uid);
        $data = $this->payModel->getAllListByfinanceUid($uid);
        foreach($data as $k => $v){
            switch($v['is_pay']){
                case 1:
                    $str = '等待支付';
                    break;
                case 2:
                    $str = '银行处理中';
                    break;
                case 3:
                    $str = '支付已完成';
                    break;
                default:
                    $str = '未知状态';
            }
            $data[$k]['status'] = $str;
        }
        $this->assign('payInfo',$data);
        $this->display();
    }
    /**
     * @Author: ludezh
     */
    public function addRecordRow(){
        $address = I('post.address');
        $htime = I('post.htime');
        $carUid = I('post.carUid');
        $carSn = I('post.carSn');
        $inspectSn = I('post.inspectSn');
        $typeSn = I('post.typeSn');
        $remark = I('post.remark');
        $total = I('post.total');
        $financeUid = session('uid');
        $add_time = date('Y-m-d H:i:s');
        $id = $this->recordModel->addNewRow($carUid, $financeUid, $inspectSn, $carSn, '', $typeSn, $add_time, $address, $htime, $total, $remark);
        if($id){
            $this->inspectModel->updateRowStatusAndFinance($inspectSn,9,$financeUid);
            $recordSn = makeEveryNumber('RS',$id);
            $this->recordModel->addRecordSn($id,$recordSn);
            $this->success('新增理赔成功');
            exit();
        }else{
            $this->error('添加新理赔报价失败');
            exit();
        }
    }

    /**
     * @Author: ludezh
     */
    public function surveyReporter(){
        $carSn = I('get.carSn');
        $inspectUid = I('get.inspectUid');
        $inspectSn = I('get.inspectSn');
        $flag = I('get.flag');
        $inspectModel = D('Settle/Inspect');
        $inspectInfo = $inspectModel->getInspectInfoByInspectSn($inspectSn,$inspectUid);
        $picArr = explode(',',$inspectInfo['header_img_list']);
        $picUrlArr = array();
        foreach($picArr as $k => $v){
            $pic = substr($v, strrpos($v, '../')+2);
            if(strlen($pic) > 25){
                $picUrlArr[$k]['pic'] = $pic;
            }
        }
//        print_r($picUrlArr);exit();
        $carModel = D('Car/Message');
        $carInfo = $carModel->getRowByCarSn($inspectInfo['car_sn']);
        $this->assign('flag',$flag);
        $this->assign('picArr',$picUrlArr);
        $this->assign('inspectInfo',$inspectInfo);
        $this->assign('carInfo',$carInfo);
        $this->display();
    }

    /**
     *根据财务人员和状态获取对应的理赔记录列表
     * 待审核/审核不通过/审核通过
     */
    public function getRecordList(){
        $status = I('post.status');
        $uid = session('uid');
        $result = $this->recordModel->getListByStatus($uid,$status);
        $this->assign('recordList',$result);

    }

    /**
     *初级审核不通过(根据情况修改对应状态：4审核不通过(客服需确认)，5审核不通过(勘察人员需确认)，7审核不通过，客服联系车主填写完整资料)
     */
    public function refusePass(){
        $financeUid = session('uid');
        $status = I('post.status');
        $remark = I('post.remark');
        $inspectSn = I('post.inspectSn');
        $rs = $this->inspectModel->changePassStatus($inspectSn,$status,$remark,$financeUid);
        if($rs){
            $this->success('操作成功,系统正在发聩');
            exit;
        }else{
            $this->error('操作失败');
            exit;
        }
    }

    /**
     *通过初级审核[生成理赔报价单(理赔记录表)]
     */
    public function surePass()
    {
        $financeUid = session('uid');
        $carUid = I('post.uid');                //车主Uid
        $car_sn = I('post.car_sn');             //车辆编号
        $policy_sn = I('post.policy_sn');       //基本保单编号
        $type_sn = I('post.type_sn');           //保险种类编号
        $inspect_sn = I('post.inspect_sn');     //理赔登记编号
        $add_time = I('post.add_time');         //事故地址
        $address = I('post.address');           //事故地址
        $amount = I('post.amount');             //理赔总金额
        $remark = I('post.remark');             //财务备注
        $create_time = date('Y-m-d H:i:s');     //理赔记录创建时间
        $id = $this->recordModel->addNewRow($carUid, $financeUid, $inspect_sn, $car_sn, $policy_sn, $type_sn, $add_time, $address, $create_time, $amount, $remark);
        $record_sn = makeEveryNumber('RS', $id);
        $rs = $this->recordModel->addRecordSnById($id, $record_sn);
        $result = $this->inspectModel->changePassStatus($inspect_sn,6,'',$financeUid); //初级审核通过,修改状态
        if ($rs && $result) {
            $this->success('成功录入系统,等待审核');
            exit;
        } else {
            $this->error('录入系统失败,稍后重试...');
            exit;
        }
    }

    /**
     *获取某一理赔登记详情(区分财务/主管获取对应的信息)
     */
    public function getOneInspect()
    {
        $inspectSn = I('post.inspectSn');
        $inspectInfo = $this->inspectModel->getRowByInspectSn($inspectSn);
        $inspectArray = array();
        $userInfo = array();
        $headerList = array();
        $bodyList = array();
        $footerList = array();
        $otherList = array();
        foreach ($inspectInfo as $k => $v) {
            $userInfo = $this->userModel->getRowByUid($v['uid']);
            $carInfo = $this->carMessageModel->getRowByCarSn($v['car_sn']);
            $inspectUser = $this->subUserModel->getRowByUid($v['inspect_uid']);
            $inspectArray['inspect_sn'] = $v['inspect_sn'];
            $inspectArray['happen_time'] = $v['happen_time'];
            $inspectArray['address'] = $v['address'];
            $headerList = explode(',', $v['header_img_list']);
            $bodyList = explode(',', $v['body_img_list']);
            $footerList = explode(',', $v['footer_img_list']);
            $otherList = explode(',', $v['other_img_list']);
            $inspectArray['base_img'] = $v['base_img'];
            $inspectArray['start_time'] = $v['start_time'];
            $inspectArray['end_time'] = $v['end_time'];
        }
        $this->assign('headerList', $headerList);
        $this->assign('bodyList', $bodyList);
        $this->assign('footerList', $footerList);
        $this->assign('otherList', $otherList);
        $this->assign('userInfo', $userInfo);
        $this->assign('subUserInfo', $inspectUser);
        $this->assign('carInfo', $carInfo);
        $this->assign('inspectInfo', $inspectArray);
        $this->display();
    }

    /**
     *根据状态获取理赔登记列表分页(财务部根据登记信息初级审核，并不需传uid),状态：1待审核，
     */
    public function getInspectList()
    {
        $status = I('post.status');
        $inspectList = $this->inspectModel->findInspectList($status);
        $inspectInfo = array();
        foreach ($inspectList as $k => $v) {
            $inspectInfo[$k]['uid'] = $v['uid'];
            $inspectInfo[$k]['inspect_uid'] = $v['inspect_uid'];
            $inspectInfo[$k]['happen_time'] = $v['happen_time'];
            $inspectInfo[$k]['inspect_sn'] = $v['inspect_sn'];
//            $inspectInfo[$k]['car_sn'] = $v['car_sn'];
//            $inspectInfo[$k]['policy_sn'] = $v['policy_sn'];
//            $inspectInfo[$k]['type_sn'] = $v['type_sn'];
        }
        $this->assign('inspectList', $inspectInfo);
        $this->display();
    }

    /**
     * 获取对应理赔的在线帮助文档
     * @param $typeSn
     * @return string
     */
    protected function getSettleHelp($typeSn)
    {
        if (empty($typeSn)) {
            return '';
        }
        return $this->policyTypeModel->getRowByTypeSn($typeSn);
    }
    
    /*
     * 待处理理赔报价
     */
    public function noChecking()
    {
        $where['is_pass'] = array('neq',3);
        $where['finance_uid'] = session('uid');
    	$un = $this -> recordModel -> where($where) -> select();
    	//print_r($un);
    	$this -> assign('un', $un);
    	$this->display('noChecking');
    }
    
    /**
     * 修改理赔单
     */
    public function reviewVerifyClaims()
    {
    	$id = $_GET['id'];
    	$data = $this -> recordModel -> where("id='".$id."'") -> find();
//    	if($data['amount'] == '' || $data['amount'] == null) {
//    		 $data['money1'] = rand(1000, 10000);
//    		 $data['money2'] = rand(1000, 10000);
//    	} else {
//    		$data['money1'] = intval($data['amount']) - 1000;
//    		$data['money2'] = intval($data['amount']) - $data['money1'];
//    	}
        $remark = explode(',',$data['remark']);
        $recordInfo = array_filter($remark);
//    	print_r($data);
    	$this -> assign('data', $recordInfo);
    	$this -> assign('recordInfo', $data);
    	$this -> display('reviewVerifyClaims');
    }
    
    /**
     * 保存理赔单修改
     */
    public function edit_post()
    {
//        print_r($_POST);exit();
//    	$_POST['is_pass'] = 1;
    	$res = true;
    	if($res) {
    		$where['uid'] = $_POST['uid'];
    		$where['finance_uid'] = $_POST['finance_uid'];
    		$where['record_sn'] = $_POST['record_sn'];
    		$data['amount'] = intval($_POST['price1']+$_POST['price2']+$_POST['price3']+$_POST['price4']+$_POST['price5']);
    		$data['remark'] = trim($_POST['text1']).",".trim($_POST['text2']).",".trim($_POST['text3']).",".trim($_POST['text4']).",".trim($_POST['text5']);
    		$data['is_pass'] = 1;
//            print_r($data);exit();
    		$res2 = $this -> recordModel -> where($where)->save($data);
    		if($res2) {
    			$this -> success('操作成功', U('/staff/record/noChecking/'));
    		}

    	} else {
    		$this -> success('操作失败');
    	}
//        if($res) {
//            $data['uid'] = $_POST['uid'];
//            $data['pay_sn'] = getRandStr(25);
//            $data['dealer_uid'] = $_POST['dealer_uid'];
//            $data['finance_uid'] = $_POST['finance_uid'];
//            $data['record_sn'] = $_POST['record_sn'];
//            $data['price'] = $_POST['amount'];
//            $data['create_time'] = date('Y-m-d H:i:s');
//            $data['is_pay'] = 1;
//            $res2 = $this -> payModel -> add($data);
//            if($res2) {
//                $this -> success('保存成功', U('/staff/record/noChecking/'));
//            }
//
//        } else {
//            $this -> success('保存失败');
//        }
    }
}