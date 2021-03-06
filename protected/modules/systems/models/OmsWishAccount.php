<?php
/**
 * @desc Aliexpress账号
 * @author Gordon
 * @since 2015-06-25
 */
class OmsWishAccount extends SystemsModel{
    
    public static function model($className = __CLASS__) {
        return parent::model($className);
    }
    
    /**
     * @desc 数据库表名
     * @see CActiveRecord::tableName()
     */
    public function tableName() {
        return 'ueb_wish_account';
    }


    /**
     * 显示所有速卖通账号ID
     */
    public function getWishAccountID(){
        $accountArr = array();
        $info = $this->getDbConnection()->createCommand()->select('wish_id')->from(self::tableName())->queryAll();
        if($info){
            foreach ($info as $value) {
                $accountArr[] = $value['wish_id'];
            }
        }

        return $accountArr;
    }


    /**
     * 更新数据
     */
    public function updateData($data, $conditions, $params){
        return $this->getDbConnection()->createCommand()->update(self::tableName(), $data, $conditions, $params);
    }


    /**
     * 插入数据
     */
    public function insertData($data){
        return $this->getDbConnection()->createCommand()->insert(self::tableName(), $data);
    }
}