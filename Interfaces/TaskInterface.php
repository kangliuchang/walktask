<?php
/**
 * 金豆任务
 *
 * User: klc
 * Date: 2020/12/10
 * Time: 17:12
 */

namespace walktask\Interfaces;


interface TaskInterface
{

    /**
     * 获取任务
     */
    public function get();

    /**
     * 完成任务
     */
    public function complete();

    /**
     * 领取奖励
     */
    public function rewards();

    /**
     * 检查任务是否完成
     */
    public function check();

}