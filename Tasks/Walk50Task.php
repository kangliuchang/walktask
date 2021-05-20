<?php
/**
 * 精神活跃-累计路程达到50公里,不低于20次路线
 * User: 小金城武
 * Date: 2021/05/17
 */
namespace walktask\Tasks;

use walktask\Interfaces\TaskInterface;

class Walk50Task implements TaskInterface
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
        $this->data = [
            //是否展示
            'member_task_is_show' => 1,
            //是否已完成
            'member_task_is_complete' => 0,
            //任务完成数量
            'member_task_complete_num' => 0,
            //任务目标
            'task_target' => 50,
            //任务第二目标
            'task_second_target' => 20,
            //用户进度
            'member_progress' => 0,
            //用户进度
            'member_second_progress' => 0,
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
            if ($memberTask && $memberTask['is_complete'] == 1)
                $this->data['member_task_is_complete'] = $memberTask['is_complete'];
            else
                $this->data['member_task_is_complete'] = (int)$this->check();

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
//                throw new \Exception('您还未完成领取新人免单任务',50000);
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
                throw new \Exception('您还未完成50公里健走',50000);
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
            $userworkModel = M('userwork');
            $userWork = $userworkModel->getUserWork("author_id = {$this->authorId}");

            //用户进度
            $this->data['member_progress'] = $userWork ? $userWork['kilometre'] : 0;
            //用户第二进度
            $this->data['member_second_progress'] = $userWork ? $userWork['total_times'] : 0;

            if ($userWork && $userWork['kilometre'] >= 50 && $userWork['total_times'] >= 20)
                return true;

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}