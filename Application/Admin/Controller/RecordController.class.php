<?php
/**
 * @Tool: PhpStorm.
 * @Company:
 * @Author: zml
 * @Since: 2016/11/21 18:18
 * @Description: ����
 */

namespace Admin\Controller;


use Common\Controller\BaseController;
use Common\Controller\CommonController;

class RecordController extends BaseController
{
    protected $recordModel;
    protected $payModel;
    protected $inspectModel;
    protected $subUserModel;
    protected $adminModel;

    public function __construct()
    {
        parent::__construct();
        $this->recordModel = D('Settle/Record');
        $this->payModel = D('Pay/Pay');
        $this->inspectModel = D('Settle/Inspect');
        $this->subUserModel = D('User/SubUser');
        $this->adminModel = D('User/Admin');
    }


    /**
     * @Author: ludezh
     */
    public function surveyReporter($flag1 = 0,$inspectSn = 0){
        $carSn = I('get.carSn');
        $inspectUid = I('get.inspectUid');
        if(empty($inspectSn)){
            $inspectSn = I('get.inspectSn');
        }
        $flag = I('get.flag');
        $inspectModel = D('Settle/Inspect');
        $inspectInfo = $inspectModel->getInspectInfoByInspectSn($inspectSn);
        $picArr = explode(',',$inspectInfo['header_img_list']);
        $picUrlArr = array();
        foreach($picArr as $k => $v){
            $picUrlArr[$k]['pic'] = substr($v, strrpos($v, '../')+2);
        }
        $carModel = D('Car/Message');
        $carInfo = $carModel->getRowByCarSn($inspectInfo['car_sn']);
        if($flag1){
            $data = array($picUrlArr,$inspectInfo,$carInfo);
            return $data;
        }else{
            $this->assign('flag',$flag);
            $this->assign('picArr',$picUrlArr);
            $this->assign('inspectInfo',$inspectInfo);
            $this->assign('carInfo',$carInfo);
            $this->display();
        }

    }

    /**
     *֪ͨ��δȷ�ϵ����ⱨ�۵��ĸ�����(����)
     */
    public function noticeStaffDeal(){
        $financeUid = I('post.financeUid');
        $recordSn = I('post.recordSn');
        $uid = session('uid');
        $subUserInfo = $this->subUserModel->getRowByUid($financeUid);   //��ȡ������Ա������Ϣ
        $userInfo = $this->adminModel->getNameByUid($uid);
        $content = '������'.$financeUid.'Ա��!,ϵͳ֪ͨ����һ�����ⱨ�۵���'.$recordSn.'��ȷ��,����۴��͵�¼ϵͳ,��ɲ���,�����ˣ�'.$userInfo.'����';
        $data = array($content,10);
        $tempId = '1';
        $rs = sendTemplateSMS($subUserInfo['phone'],$data,$tempId);
        if($rs){
            $this->success('ϵͳ���ڻ���֪ͨ��...');
            exit;
        }else{
            $this->error('֪ͨ��ƫ��,����绰��ϵ������Ա');
            exit;
        }
    }

    /**
     *ͬ������ͨ��/���ܶ������[���ͨ��(��ɶ�Ӧ�Ļ�����֧������ǩ��)(���ڴ����)
     */
    public function agreePass(){
        $status = I('post.status');
        if($status == 3){
            $remark = I('post.remark');
            $recordSn = I('post.recordSn');
            $rs = $this->recordModel->changePassByRecordSn($recordSn,$status,$remark);
            if($rs){
                $carUid = I('post.Uid');
                $dealUid = session('dealUid');
                $financeUid = I('post.financeUid');
                $payCode = I('post.payCode');
                $payType = empty($payCode) ? '����֧��' : I('post.payCode');
                $price = I('post.price');
                $createTime = date('Y-m-d H:i:s');
                $remark = I('post.remark');
                $id = $this->payModel->addNewRow($carUid,$dealUid,$financeUid,$recordSn,$payType,$price,$createTime,$status,$remark);
                $paySn = makeEveryNumber('PS',$id);
                $result = $this->payModel->addPaySnById($id,$paySn);
                if($result){
                    $this->success('�ɹ�����ͬ������');
                    exit;
                }else{
                    $this->error('ͬ��ʧЧ');
                    exit;
                }
            }else{
                $this->error('���ʧ��');
                exit;
            }
        }else{
            $this->error('�Ƿ�����');
            exit;
        }
    }

    /**
     *�ܾ�����ͨ��(���ڴ����)
     */
    public function cancelPass(){
        $status = I('post.status');
        $remark = I('posy.remark');
        $recordSn = I('post.recordSn');
        $inspectSn = I('post.inspectSn');
        $dealerUid = session('uid');
        if($status == 2){
            //�޸����ⱨ�۵���״̬����ע��������
            $rs = $this->recordModel->refusePassByRecordSn($recordSn,$dealerUid,$status,$remark);
            if($rs){
                $this->success('�ɹ��ܾ�,ϵͳ���ڷ���');
                exit;
            }else{
                $this->error('�ܾ�ʧ��');
                exit;
            }
        }else{
            //���״̬����2����ɾ����ɵ����ⱨ�۵�,���޸�����ǼǱ��״̬��
            //4��˲�ͨ��(�ͷ���ȷ��)��5��˲�ͨ��(������Ա��ȷ��)��7��˲�ͨ��ͷ���ϵ������д��������
            $result = $this->recordModel->deleteOldRecordByInspectSnRecordSn($inspectSn,$recordSn);
            if($result){
                $rs = $this->inspectModel->changeStatusByDealer($inspectSn,$dealerUid,$status,$remark);
                if($rs){
                    $this->success('�����ɹ�,ϵͳ���ڷ���');
                    exit;
                }else{
                    $this->error('����ʧ��');
                    exit;
                }
            }
        }
    }

    /**
     *���״̬��ȡ���ⱨ�۵��б�(��˲�ͨ��/���ͨ��)
     */
    public function getRecordList(){
        $uid = session('uid');
        $status = I('post.status');
        $recordList = $this->recordModel->getRowsByDealerUid($uid,$status);
        if(empty($recordList)){
            $content = '�������ⱨ�ۼ�¼';
        }
        $this->assign('recordList',$recordList);
        $this->assign('content',$content);
        $this->display();
    }

    /**
     *��ȡ��������ⱨ�۵����м�¼
     */
    public function getWaitingList(){
        $type = I("get.type");
        if($type==1){
            $where['is_pass'] = 1;
        }
        else{
            $where['is_pass'] = array('eq', '3');
        }
        $recordList = $this->recordModel->where($where)->select();

        if(empty($recordList)){
            $content = '暂无查询结果';
        }
        else{
            foreach($recordList as $k=>$v){
                switch($v['is_pass']){
                    case 1:
                        $recordList[$k]['type'] = "待审核";
                        break;
                    case 2:
                        $recordList[$k]['type'] = "审核不通过";
                        break;
                    case 3:
                        $recordList[$k]['type'] = "审核通过";
                        break;
                    default:
                        break;
                }
            }
        }
//        $this->assign('recordList',  json_encode($recordList));
        $this->assign('recordList1',  $recordList);
        $this->assign('content',$content);
        $this->display("claimingManage");
    }
    /**
     *	�������ⵥ
     */    
    public function lookVerifyClaims()
    {
        //判断是提交还是页面
        if($_POST){
//            print_r($_POST);exit();
            $result = array('error'=>0,"content"=>"操 作 失 败");
            $info['id'] = I('post.id');
            $inspect_sn = I('post.inspect_sn');
            $info['is_pass'] = I("post.value","","intval");
            $remark = I("post.remark");
            if($info['is_pass'] != 3){
                if($info['is_pass'] == 2){
                    $info['deal_remark'] = $remark;
                    $res = $this->recordModel->save($info);
                    $res1 = 1;
                }else{
                    $res = $this->inspectModel->updateStatusBySn($inspect_sn,$info['is_pass']);
                    $res1 = 1;
                }
            }
            else{
//                echo $inspect_sn;exit();
                $re = $this->inspectModel->updateStatusBySn($inspect_sn,10);
//                print_r($re);exit();
                $info['deal_remark'] = $remark;
                $res = $this->recordModel->save($info);
                $data['uid'] = I("post.uid");
                //25位随机字符
                $code =array_merge(range('a','z'),range('A','Z'),range(1,9));
                $str = "";
                for($i=0;$i<25;$i++){
                    $str .= $code[rand(0, count($code)-1)];
                }
                $data['pay_sn'] = $str;
                $data['dealer_uid'] = $_SESSION["uid"];
                $data['finance_uid'] = I("post.finance_id");
                $data['record_sn'] = I("post.record_sn");
                $data['price'] = rand(1000,10000);
                $data['pay_type'] = '银联支付';
                $data['create_time'] = date("Y-m-d H:i:s",time());
                $data['is_pay'] = 1;
                $data['remark'] = $remark;
                $res1 = M('settle_pay')->add($data);
            }
            if($res&&$res1){
                $result['error'] = 1;
                $result['content'] = "修 改 成 功";
            }
            print_r(json_encode($result));exit;
        
        }
        else{
            $flag = I("get.flag");
            $id = I("get.id");
            $inspectSn = I("get.inspectSn");
            $info = $this->recordModel->find($id);
            $data = $this->surveyReporter($inspectSn);
            $this->assign('picArr',$data[0]);
            $this->assign('inspectInfo',$data[1]);
            $this->assign('carInfo',$data[2]);
            $this->assign("info",$info);
            $this->assign("id",$id);
            $this->assign("flag",$flag);
        }
        $this->display();
    }
    /**
     * ���챨��
     */
    public function lookSurveyReporter()
    {
        $id = I("get.id");
        $info = $this->recordModel->table("safe_car_settle_record r")->join(" safe_car_settle_inspect s ON r.record_sn=s.inspect_sn ")
                ->join(" safe_car_user u ON r.uid=u.uid ")
                ->join(" safe_car_message m ON r.car_sn=m.car_sn ")
                ->field("r.*,s.*,u.*,m.*")->where(array("r.id"=>$id))->find();
        $this->assign("info",$info);
        $this->display();
    }
}