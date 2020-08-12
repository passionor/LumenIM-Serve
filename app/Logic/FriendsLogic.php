<?php

namespace App\Logic;

use App\Models\User;
use App\Models\UsersFriends;
use App\Models\UsersFriendsApply;
use Illuminate\Support\Facades\DB;

/**
 * 好友逻辑处理层
 *
 * Class FriendsLogic
 * @package App\Logic
 */
class FriendsLogic extends Logic
{

    /**
     * 创建好友的申请
     *
     * @param int $user_id 用户ID
     * @param int $friend_id 好友ID
     * @param string $remarks 好友申请备注
     * @return bool
     */
    public static function addFriendApply(int $user_id, int $friend_id, string $remarks)
    {
        $result = UsersFriendsApply::where('user_id', $user_id)->where('friend_id', $friend_id)->where('status', 0)->first();
        if ($result) {
            $result->updated_at = date('Y-m-d H:i:s');
            $result->save();
        } else {
            $result = UsersFriendsApply::create([
                'user_id' => $user_id,
                'friend_id' => $friend_id,
                'status' => 0,
                'remarks' => $remarks,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        return $result ? true : false;
    }

    /**
     * 处理好友的申请
     *
     * @param int $user_id 当前用户ID
     * @param int $apply_id 申请记录ID
     * @param int $status 申请状态(1:已同意  2:已拒绝)
     * @param string $remarks 备注信息(当$status =1 时代表好友昵称备注，$status =2时代表拒绝原因)
     * @return bool
     */
    public static function handleFriendApply(int $user_id, int $apply_id, int $status, $remarks = '')
    {
        $info = UsersFriendsApply::where('id', $apply_id)->where('friend_id', $user_id)->where('status', 0)->first();
        if (!$info) {
            return false;
        }

        if ($status == 1) {//同意添加好友
            //查询是否存在好友记录
            $isFriend = UsersFriends::select('id', 'user1', 'user2', 'active', 'status')->where(function ($query) use ($info) {
                $query->where('user1', '=', $info->user_id)->where('user2', '=', $info->friend_id);
            })->orWhere(function ($query) use ($info) {
                $query->where('user2', '=', $info->user_id)->where('user1', '=', $info->friend_id);
            })->first();

            DB::beginTransaction();
            try {
                $res = UsersFriendsApply::where('id', $apply_id)->update(['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
                if (!$res) {
                    throw new \Exception('更新好友申请表信息失败');
                }

                if ($isFriend) {
                    $active = ($isFriend->user1 == $info->user_id && $isFriend->user2 == $info->friend_id) ? 1 : 2;
                    if (!UsersFriends::where('id', $isFriend->id)->update(['active' => $active, 'status' => 1])) {
                        throw new \Exception('更新好友关系信息失败');
                    }
                } else {
                    $user1 = $info->user_id < $info->friend_id ? $info->user_id : $info->friend_id;
                    $user2 = $info->user_id < $info->friend_id ? $info->friend_id : $info->user_id;

                    //好友昵称
                    $friend_nickname = User::where('id', $info->friend_id)->value('nickname');
                    $insRes = UsersFriends::create([
                        'user1' => $user1,
                        'user2' => $user2,
                        'user1_remark' => $user1 == $user_id ? $remarks : $friend_nickname,
                        'user2_remark' => $user2 == $user_id ? $remarks : $friend_nickname,
                        'active' => $user1 == $user_id ? 2 : 1,
                        'status' => 1,
                        'agree_time' => date('Y-m-d H:i:s'),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);

                    if (!$insRes) {
                        throw new \Exception('创建好友关系失败');
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return false;
            }

            return true;
        } else if ($status == 2) {//拒绝添加好友
            $res = UsersFriendsApply::where('id', $apply_id)->update(['status' => 2, 'updated_at' => date('Y-m-d H:i:s'), 'reason' => $remarks]);
            return $res ? true : false;
        }

        return false;
    }

    /**
     * 解除好友关系
     *
     * @param int $user_id 用户ID
     * @param int $friend_id 好友ID
     * @return bool
     */
    public static function removeFriend(int $user_id, int $friend_id)
    {
        if (!UsersFriends::checkFriends($user_id, $friend_id)) {
            return false;
        }

        $data = ['status' => 0];
        if (UsersFriends::where('user1', $user_id)->where('user2', $friend_id)->update($data) || UsersFriends::where('user2', $user_id)->where('user1', $friend_id)->update($data)) {
            return true;
        }

        return false;
    }

    /**
     * 获取用户好友申请记录
     *
     * @param int $user_id 用户ID
     * @param int $type 获取数据类型  1:好友申请记录 2:我的申请记录
     * @param int $page 分页数
     * @param int $page_size 分页大小
     * @return array
     */
    public function friendApplyRecords(int $user_id, $type = 1, $page = 1, $page_size = 30)
    {
        $countSqlObj = UsersFriendsApply::select();
        $rowsSqlObj = UsersFriendsApply::select([
            'users_friends_apply.id',
            'users_friends_apply.status',
            'users_friends_apply.remarks',
            'users_friends_apply.reason',
            'users.nickname',
            'users.avatar',
            'users.mobile',
            'users_friends_apply.user_id',
            'users_friends_apply.friend_id',
            'users_friends_apply.created_at'
        ]);

        if ($type == 1) {
            $rowsSqlObj->leftJoin('users', 'users.id', '=', 'users_friends_apply.user_id');
            $countSqlObj->where('users_friends_apply.friend_id', $user_id);
            $rowsSqlObj->where('users_friends_apply.friend_id', $user_id);
        } else {
            $rowsSqlObj->leftJoin('users', 'users.id', '=', 'users_friends_apply.friend_id');
            $countSqlObj->where('users_friends_apply.user_id', $user_id);
            $rowsSqlObj->where('users_friends_apply.user_id', $user_id);
        }

        $count = $countSqlObj->count();
        $rows = [];
        if ($count > 0) {
            $rows = $rowsSqlObj->orderBy('users_friends_apply.id', 'desc')->forPage($page, $page_size)->get()->toArray();
        }

        return $this->packData($rows, $count, $page, $page_size);
    }

    /**
     * 编辑好友备注信息
     *
     * @param int $user_id 用户ID
     * @param int $friend_id 朋友ID
     * @param string $remarks 好友备注
     * @return bool
     */
    public static function editFriendRemark(int $user_id, int $friend_id, string $remarks)
    {
        if (UsersFriends::where('user1', $user_id)->where('user2', $friend_id)->update(['user1_remark' => $remarks]) ||
            UsersFriends::where('user1', $friend_id)->where('user2', $user_id)->update(['user2_remark' => $remarks])) {
            return true;
        }

        return false;
    }
}