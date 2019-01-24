<?php

namespace OfflineAgency\MongoAutoSync\Http\Models;

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

	public function getCollection(){
		return $this->collection;
	}

}