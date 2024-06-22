<?php
namespace lib;

class Order
{
    public static function freeze($trade_no){
        global $DB;
        $row = $DB->find('order', 'uid,getmoney,status', ['trade_no'=>$trade_no]);
        if(!$row)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if($row['status']!=1)
            return ['code'=>-1, 'msg'=>'只支持冻结已支付状态的订单'];
        if($row['getmoney']>0){
            changeUserMoney($row['uid'], $row['getmoney'], false, '订单冻结', $trade_no);
            $DB->exec("update pre_order set status='3' where trade_no='$trade_no'");
        }
        return ['code'=>0, 'msg'=>'已成功从UID:'.$row['uid'].'冻结'.$row['getmoney'].'元余额'];
    }

    public static function unfreeze($trade_no){
        global $DB;
        $row = $DB->find('order', 'uid,getmoney,status', ['trade_no'=>$trade_no]);
        if(!$row)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if($row['status']!=3)
            return ['code'=>-1, 'msg'=>'只支持解冻已冻结状态的订单'];
        if($row['getmoney']>0){
            changeUserMoney($row['uid'], $row['getmoney'], true, '订单解冻', $trade_no);
            $DB->exec("update pre_order set status='1' where trade_no='$trade_no'");
        }
        return ['code'=>0, 'msg'=>'已成功为UID:'.$row['uid'].'恢复'.$row['getmoney'].'元余额'];
    }

    public static function refund_info($trade_no, $api = 0, $uid = 0){
        global $DB;
        $where = ['trade_no'=>$trade_no];
        if($uid > 0) $where['uid'] = $uid;
        $row = $DB->find('order', 'uid,api_trade_no,channel,money,status', $where);
        if(!$row)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if($row['status']!=1&&$row['status']!=3)
            return ['code'=>-1, 'msg'=>'只支持退款已支付状态的订单'];

        if($api==1){
            if(!$row['api_trade_no']) return ['code'=>-1, 'msg'=>'接口订单号不存在'];
            $channel = \lib\Channel::get($row['channel']);
            if(!$channel) return ['code'=>-1, 'msg'=>'当前支付通道信息不存在'];
            if(\lib\Plugin::isrefund($channel['plugin'])==false){
                return ['code'=>-1, 'msg'=>'当前支付通道不支持API退款'];
            }
        }

        return ['code'=>0, 'money'=>$row['money']];
    }

    public static function refund($trade_no, $money, $api = 0, $uid = 0){
        global $DB;

        $where = ['trade_no'=>$trade_no];
        if($uid > 0) $where['uid'] = $uid;
        $row = $DB->find('order', 'uid,channel,money,getmoney,status', $where);
        if(!$row)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if($row['status']!=1&&$row['status']!=3)
            return ['code'=>-1, 'msg'=>'只支持退款已支付状态的订单'];
        if($money>$row['money']) return ['code'=>-1, 'msg'=>'退款金额不能大于订单金额'];

        $mode = $DB->findColumn('channel', 'mode', ['id'=>$row['channel']]);
        if($row['status'] == 3 || $mode == 1){
            $reducemoney = 0;
        }elseif($money == $row['money'] || $money >= $row['getmoney']){
            $reducemoney = $row['getmoney'];
        }else{
            $reducemoney = $money;
        }

        if($uid > 0 && $reducemoney > 0){
            $usermoney = $DB->findColumn('user', 'money', ['uid'=>$uid]);
            if($reducemoney > $usermoney){
                return ['code'=>-1, 'msg'=>'商户余额不足，请先充值'];
            }
        }

        if($api == 1){
            $message = null;
            if(!\lib\Plugin::refund($trade_no, $money, $message)){
                return ['code'=>-1, 'msg'=>'退款失败：'.$message];
            }
        }
        if($reducemoney > 0){
            changeUserMoney($row['uid'], $reducemoney, false, '订单退款', $trade_no);
        }
        $DB->exec("update pre_order set status='2' where trade_no='$trade_no'");
        
        return ['code'=>0, 'uid'=>$row['uid'], 'money'=>$money, 'reducemoney'=>$reducemoney];
    }
}