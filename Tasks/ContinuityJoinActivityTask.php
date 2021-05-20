<?php
/**
 * 连续7天参与团队路线
 * User: 小金城武
 * Date: 2021/05/17
 */
namespace walktask\tasks;

use walktask\Interfaces\TaskInterface;

class ContinuityJoinActivityTask implements TaskInterface
{

    public $authorId;
    public $task = [];
    public $memberTask = [];
    public $data = [];

    /**
     * 获取任务
     */
    public function get()
    {
        //连续七天参与团队路线,可获得奖励
        $this->data = [
            //用户是否展示此任务
            'member_task_is_show' => 1,
            //用户是否已完成
            'member_task_is_complete' => 0,
            //用户任务完成数量
            'member_task_complete_num' => 0,
            //任务目标
            'task_target' => 7,
            //用户进度
            'member_progress' => 0,
        ];
        try {
            $authorTasksModel = M('walk_author_tasks');
            //需要判断展示情况
            //先判断多次完成的情况
            //先取符合条件的已完成已领取的次数,如果 大于最大完成次数, 展示状态设为0
            $this->data['member_task_complete_num'] = $authorTasksModel->getTasksCompleteNum($this->authorId,$this->task['id'],$this->task['task_type'],$this->task['task_cate']);
            if ($this->data['member_task_complete_num'] >= $this->task['complete_num']){
                $this->data['member_task_is_show'] = 0;
                return $this->data;
            }

            //获取用户领取的任务
            //取一条未领取奖励的任务,如果没有代表用户没有领过任务(注意这里没有判断完成状态,不管完没完成都会取到该任务)
            $memberTask = $authorTasksModel->getTasksReceive($this->authorId,$this->task['id'],$this->task['task_type'],$this->task['task_cate'],0);

            // 本条任务的完成情况
            // 优先使用本条任务的完成情况, 如果本条任务未完成时
            // 需要一定条件才算完成的 根据is_check去 判断完成情况
            // 不需要条件完成的, 返回0
            if ($memberTask && $memberTask['is_complete'] == 1){
                $this->data['member_task_is_complete'] = $memberTask['is_complete'];
                $this->data['member_progress'] = 7;
            }else{
                $this->data['member_task_is_complete'] = (int)$this->check();
            }

            return $this->data;
        } catch (\Exception $e) {
            return $this->data;
        }
    }

    /**
     * 完成任务
     * @return bool
     * @throws
     */
    public function complete()
    {
//        try {
//            //检查有没有完成
//            if (!$this->check())
//                throw new \Exception('',50000);
//
//            //修改任务完成记录
//            if (!$res)
//                throw new \Exception('用户任务变更失败');
//
//            return true;
//        } catch (\Exception $e) {
//            throw new \Exception($e->getMessage(),$e->getCode());
//        }
    }

    /**
     * 领取奖励
     * 这里 就是发放豆的逻辑
     * @return bool
     * @throws
     */
    public function rewards()
    {
        try {
            $authorTasksModel = M('walk_author_tasks');
            //获取用户一条同类型的没有领取奖励的任务(不管完没完成,只要没领取奖励,因为之后发放奖励会判断完成状态)
            $this->memberTask = $authorTasksModel->getTasksReceive($this->authorId,$this->task['id'],$this->task['task_type'],$this->task['task_cate']);
            if (!$this->memberTask)
                $this->memberTask = $authorTasksModel->setMemberTasks($this->authorId,$this->task);
            //查任务奖励商品
            $goods = M('walk_task_goods')->getGoodsByTaskId($this->task['id']);
            if (!$goods)
                throw new \Exception('获取不到奖励商品',50000);
            //检查库存
            if ($goods['goods_stock'] <= 0)
                throw new \Exception('商品库存不足',50000);
            //查用户地址
            $address = M('walk_author_address')->where("author_id = {$this->authorId}")->find();
            if (!$address)
                throw new \Exception('请先绑定收货地址',50000);
            //检查有没有完成
            if (!$this->check())
                throw new \Exception('您还未完成连续7天健走',50000);
            //检查助力次数
            if ($this->task['help_num'] > 0){
                $heplNum = M('walk_author_help')->getAuthorHelpNum($this->authorId,$this->task['activity_id'],$this->task['id']);
                if ($heplNum < $this->task['help_num'])
                    throw new \Exception('好友助力未完成,请先完成好友助力',50000);
            }
            try{
                //领取奖励
                $authorTasksModel->beginTransaction();
                //修改任务完成记录
                $orderId = date('YmdHis').str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $res = $authorTasksModel->where('id',$this->memberTask['id'])->update([
                    'is_complete' => 1,
                    'complete_at' => time(),
                    'is_receive' => 1,
                    'receive_at' => time(),
                    'order_id' => $orderId,
                    'created_at' => time()
                ]);
                if (!$res)
                    throw new \Exception('用户任务变更失败');

                //生成一条领取任务的奖励订单
                $res = M('walk_task_reward_orders')->setTaskOrders($this->authorId,$orderId,$goods,$address);
                if (!$res)
                    throw new \Exception('订单创建失败');

                //减库存
                $res = M('walk_task_goods')->where("id = {$goods['id']}")->setDec('goods_stock',1);
                if (!$res)
                    throw new \Exception('减库存失败');

                $authorTasksModel->commit();
            }catch (\Exception $e){
                $authorTasksModel->rollback();
                throw new \Exception($e->getMessage(),$e->getCode());
            }
            return true;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(),$e->getCode());
        }
    }

    /**
     * 检查任务是否完成
     */
    public function check()
    {
        try {
            //查参与路线数据
            $recode = M('individual_work')->field("id,create_at")->where("author_id = {$this->authorId} AND created_at > {$this->task['start_at']} AND created_at < {$this->task['end_at']} AND act_id > 0")->order('created_at desc')->select();
            if (!$recode)
                return false;
            //看看有没有连续七天, 连续了多少天
            //查今天是本年的第几天
            $zday = date('z');
            //当前连续天数
            $continuityDay = 0;
            //连续天数数组
            $continuityDayArr = [];
            //曾经连续天数
            $continuityDayed = 0;
            //组装数组
            foreach ($recode as $item){
                $zDayKey = date('z',$item['create_at']);
                //如果今天跑了,当前连续天数=1
                if ($zDayKey == $zday){
                    $continuityDay = 1;
                }
                if (!in_array($zDayKey,$continuityDayArr)){
                    $continuityDayArr[] = $zDayKey;
                }
            }
            ksort($continuityDayArr);
            //查当前连续了几天
            $key = 1;
            while (1){
                if (in_array($zday-$key,$continuityDayArr)){
                    $key++;
                    $continuityDay++;
                }else{
                    break;
                }
            }
            //查之前有没有完成过连续7天
            foreach ($continuityDayArr as $item){
                if (in_array($item-1,$continuityDayArr)){
                    $continuityDayed++;
                    if ($continuityDayed >= 7)
                        break;
                }else{
                    $continuityDayed = 1;
                }
            }

            //用户完成情况, 如果之前有连续过7天, 优先用连续7天, 如果没有的话优先用当前连续天数
            if ($continuityDayed >= 7){
                $this->data['member_progress'] = $continuityDayed;
                return true;
            }else{
                $this->data['member_progress'] = $continuityDay;
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}