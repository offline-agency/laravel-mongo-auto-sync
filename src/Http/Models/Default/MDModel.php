<?php

namespace App\Models\Auth\Def;

use App\Traits\OaModelAdditionalMethod;
use App\Traits\OaMongoSyncTrait;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class MDModel extends Eloquent
{
    protected $connection = 'mongodb';
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    use OaMongoSyncTrait, OaModelAdditionalMethod;

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