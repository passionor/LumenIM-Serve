<?php

namespace App\Models\Article;

use App\Models\BaseModel;
class ArticleAnnex extends BaseModel
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'article_annex';

    /**
     * 不能被批量赋值的属性
     *
     * @var array
     */
    protected $guarded = ['id'];
}
