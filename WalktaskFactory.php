<?php
/**
 * 任务工厂
 * User: klc
 * Date: 2020/12/10
 * Time: 17:49
 */
namespace walktask;

use walktask\Tasks\ContinuityJoinActivityTask;
use walktask\Tasks\GowalkTask;
use walktask\Tasks\TeamPublicActivityTask;
use walktask\Tasks\teamprivateactivitytask;
use walktask\Tasks\teamactivitywithfriendstask;
use walktask\Tasks\Walk20Task;
use walktask\Tasks\Walk50Task;
use walktask\Tasks\Walk300Task;

class WalktaskFactory
{

    public static function factory($taskCate)
    {
        switch ($taskCate){
            case 'continuity_join_activity':
                //连续7天参与团队路线
                return new ContinuityJoinActivityTask();
                break;
            case 'author_gowalk':
                //首次体验健走
                return new GowalkTask();
                break;
            case 'author_team_public_activity':
                //任一路线列表路线完成并分享
                return new TeamPublicActivityTask();
                break;
            case 'author_team_private_activity':
                //加入团队完成任一专属团队路线并分享
                return new teamprivateactivitytask();
                break;
            case 'author_team_activity_with_friends':
                //邀请好友共同参与团队健走路线6次
                return new teamactivitywithfriendstask();
                break;
            case 'author_walk_20':
                //小有成就-累计路程达到20公里,不低于10次路线
                return new Walk20Task();
                break;
            case 'author_walk_50':
                //精神活跃-累计路程达到50公里,不低于20次路线
                return new Walk50Task();
                break;
            case 'author_walk_300':
                //健步如飞-累计里程达到300公里,不低于50次路线
                return new Walk300Task();
                break;
            default:
                throw new \Exception('获取不到相关任务');
                break;
        }
    }

}