<?php

namespace App\Model;

class OrderNoteModel extends HomeModel
{
    protected ?string $table = 'order_note';

    public function getAddTimeAttribute($value)
    {
        if (!empty($value)) {
            return date("Y-m-d H:i:s", $value);
        }
        return '';
    }

    // 关联item表  order_note_item
    public function item()
    {
        return $this->hasMany(OrderNoteItemModel::class, 'note_id', 'id');
    }

    // 收件人 order_note_receiver
    public function receiver()
    {
        return $this->hasOne(OrderNoteReceiverModel::class, 'note_id', 'id');
    }

    // 发件人 order_note_sender
    public function sender()
    {
        return $this->hasOne(OrderNoteSenderModel::class, 'note_id', 'id');
    }

    // 客户 member
    public function member()
    {
        return $this->hasOne(MemberModel::class, 'uid', 'member_uid');
    }

    // 物流产品  product
    public function product()
    {
        return $this->hasOne(ProductModel::class, 'pro_id', 'pro_id');
    }

}
