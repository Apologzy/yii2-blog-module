<?php

namespace apologzy\blog\models;

use phpDocumentor\Reflection\Types\This;
use common\components\behaviors\StatusBehavior;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\UploadedFile;
use common\models\User;
use common\models\ImageManager;

/**
 * This is the model class for table "blog".
 *
 * @property int $id
 * @property string $title
 * @property string|null $text
 * @property string $url
 * @property string $date_create
 * @property string $date_update
 * @property string $image
 * @property int $status_id
 * @property int $sort
 */
class Blog extends ActiveRecord
{
    const STATUS_LIST = ['off', 'on'];
    const IMAGES_SIZE = [
        ['50','50'],
        ['800',null],
    ];
    public $tags_array;
    public $file;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'blog';
    }

    public function behaviors()
    {
        return [
           'TimestampBehavior' => [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'date_create',
                'updatedAtAttribute' => 'date_update',
                'value' => new Expression('NOW()'),
            ],
            'StatusBehavior' => [
                'class' => StatusBehavior::className(),
                'statusList' => self::STATUS_LIST,
            ],
        ];
    }
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title', 'url'], 'required'],
            [['text'], 'string'],
            [['url'], 'unique'],
            [['status_id', 'sort'], 'integer'],
            [['sort'], 'integer', 'max' => 99, 'min' => 1],
            [['title', 'url'], 'string', 'max' => 150],
            [['image'], 'string', 'max' => 100],
            [['file'], 'image'],
            [['tags_array', 'date_create', 'date_update'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Заголовок',
            'text' => 'Текст',
            'url' => 'ЧПУ',
            'status_id' => 'Статуст',
            'sort' => 'Сортировка',
            'tags_array' => 'Тэги',
            'tagsAsString' => 'Теги',
            'author.username' => 'Имя Автора',
            'author.email' => 'Email',
            'date_update' => 'Обнавлено',
            'date_create' => 'Созданно',
            'image' => 'Изображение',
            'file' => 'Изображение'
        ];
    }

    public function getAuthor () {
     return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    public function getImages() {
        return $this->hasMany(ImageManager::className(), ['item_id' => 'id'])->andWhere(['class' => self::tableName()])->orderBy('sort');
    }

    public function getImagesLinks()
    {
        return ArrayHelper::getColumn($this->images,'imageUrl');
    }

    public function getImagesLinksData()
    {
        return ArrayHelper::toArray($this->images,[
                ImageManager::className() => [
                    'caption'=>'name',
                    'key'=>'id',
                ]]
        );
    }

    public function getBlogTag() {
        return $this->hasMany(BlogTag::className(), ['blog_id' => 'id']);
    }

    public function getTags() {
        return $this->hasMany(Tag::className(), ['id' => 'tag_id'])->via('blogTag');
    }

    public function getTagsAsString()
    {
        $arr = \yii\helpers\ArrayHelper::map($this->tags,'id', 'name');
        return implode(', ', $arr);
    }

    public function getSmallImage()
    {
        if ($this->image) {
            $path = str_replace('admin.','',Url::home(true)).'uploads/images/blog/50x50/'.$this->image;
        } else {
            $path = str_replace('admin.','',Url::home(true)).'uploads/images/nophoto.svg';
        }

        return $path;
    }


    public function afterFind()
    {
        parent::afterFind();
        $this->tags_array = $this->tags;
    }

    public function beforeSave($insert)
    {
        if($file = UploadedFile::getInstance($this, 'file')){
            $dir = Yii::getAlias('@images').'/blog/';
            if(file_exists($dir.$this->image)){
                if(!empty($this->image)) {
                    unlink($dir.$this->image);
                }

            }
            if(file_exists($dir.'50x50/'.$this->image)){
                if(!empty($this->image)) {
                    unlink($dir.'50x50/'.$this->image);
                }

            }
            if(file_exists($dir.'800x/'.$this->image)){
                if(!empty($this->image)) {
                    unlink($dir.'800x/'.$this->image);
                }

            }
            $this->image = strtotime('now').'_'.Yii::$app->getSecurity()->generateRandomString(6)  . '.' . $file->extension;
            $file->saveAs($dir.$this->image);
            $imag = Yii::$app->image->load($dir.$this->image);
            $imag->background('#fff',0);
            $imag->resize('50','50', Yii\image\drivers\Image::INVERSE);
            $imag->crop('50','50');
            $imag->save($dir.'50x50/'.$this->image, 90);
            $imag = Yii::$app->image->load($dir.$this->image);
            $imag->background('#fff',0);
            $imag->resize('800',null, Yii\image\drivers\Image::INVERSE);
            $imag->save($dir.'800x/'.$this->image, 90);
        }
        return parent::beforeSave($insert); // TODO: Change the autogenerated stub
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $arr = \yii\helpers\ArrayHelper::map($this->tags,'id','id');
        foreach ($this->tags_array as $one){
            if(!in_array($one,$arr)){
                $model = new BlogTag();
                $model->blog_id = $this->id;
                $model->tag_id = $one;
                $model->save();
            }
            if(isset($arr[$one])){
                unset($arr[$one]);
            }
        }
        BlogTag::deleteAll(['tag_id'=>$arr]);
    }

        public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            $dir = Yii::getAlias('@images').'/blog/';
            if(file_exists($dir.$this->image)){
                unlink($dir.$this->image);
            }
            foreach (self::IMAGES_SIZE as $size){
                $size_dir = $size[0].'x';
                if($size[1] !== null)
                    $size_dir .= $size[1];
                if(file_exists($dir.$this->image)){
                    unlink($dir.$size_dir.'/'.$this->image);
                }
            }
            BlogTag::deleteAll(['blog_id'=>$this->id]);
            return true;
        } else {
            return false;
        }
    }


}

