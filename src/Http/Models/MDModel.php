<?php

namespace OfflineAgency\MongoAutoSync\Http\Models;

use DateTime;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use OfflineAgency\MongoAutoSync\Traits\ModelAdditionalMethod;
use OfflineAgency\MongoAutoSync\Traits\MongoSyncTrait;

class MDModel extends Eloquent
{
	protected $connection = 'mongodb';
	protected $dates = ['deleted_at', 'created_at', 'updated_at'];
	use MongoSyncTrait, ModelAdditionalMethod;

	/**
	 * Overload model __construct.
	 *
	 *  (Optional)
	 */


	public function __construct(array $attributes = [])
	{

		parent::__construct($attributes);
	}

	/**
	 * Overload model save.
	 *
	 * $name_equals string Assert User's name (Optional)
	 */
	public function save(array $options = array())
	{

		// Do great things...

		parent::save($options); // Calls Default Save
	}

	public function delete()
	{
		$options = array();
		parent::save($options); // Calls Default Save
		// Do great things...

		parent::delete(); // Calls Default Save
	}

	/**
	 * @return mixed
	 */
	public function getId(){
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getCollection(){
		return $this->collection;
	}

	/**
	 * https://github.com/jenssegers/laravel-mongodb/issues/1493
	 *
	 * @param int $size
	 *
	 * @return mixed
	 */
	public function getRandom($size = 3){
		$now = new DateTime('now');
		$tags = parent::raw(function($collection) use ($size){
			return $collection->aggregate([ ['$sample' => ['size' => $size]] ]);
		});
		return $this->fixBson($tags);
	}

	/**
	 * @param $item
	 *
	 * @return array
	 */
	function formatTypeBson($item) {

		if (isset($item) && gettype($item) == "object" && (get_class($item) == "MongoDB\Model\BSONArray" || get_class($item) == "MongoDB\Model\BSONDocument")) {

			return (array)$item->bsonSerialize();

		} else if (isset($item) && gettype($item) == "object" && get_class($item) == "MongoDB\BSON\UTCDateTime") {

			return $item->toDateTime();
		}

		return $item;
	}

	/**
	 * @param $tags
	 *
	 * @return mixed
	 */
	function fixBson($tags){
		foreach ($tags as $item) {

			$attributes = [];
			foreach ($item->attributes as $key => $attribute){
				$attributes[$key] = $this->formatTypeBson($attribute);
			}
			$item->attributes = $attributes;
		}
		return $tags;
	}
}