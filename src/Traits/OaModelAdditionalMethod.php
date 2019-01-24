<?php

namespace App\Traits;


use Exception;
use Extensions\CustomCollection;

trait OaModelAdditionalMethod {


	public function newCollection( array $models = Array() ) {
		return new CustomCollection( $models );
	}

	/**
	 * @return array
	 */
	public function getItems(): array {
		return $this->items;
	}

	/**
	 * @return array
	 */
	public function getMongoRelation(): array {
		if ( ! empty( $this->mongoRelation ) ) {
			return $this->mongoRelation;
		} else {
			return array();
		}
	}


	/**
	 * @return array
	 */
	public function getPageMetaTag() {
		$collection_name  = $this->collection;
		$meta_content     = [];
		$meta_value       = [];
		$meta_key         = [];
		$title            = "";
		$description      = "";
		$meta_description = "";
		$fb_id = env('FB_ID');
        $img_url ="";

		switch ( $collection_name ) {
			case( $collection_name == "article" ):
				$meta_content     = [
					'article',
					$this->author,
					$this->updated_at,
					$this->updated_at,
					'666',
					'920',
					'secure_image.png',
				];
				$meta_value       = [
					'og:type',
					'article:author',
					'article:modified_time',
					'article:published_time',
					'og:image:height',
					'og:image:width',
					'og:image:secure_url',
				];
				$meta_key         = [
					'property',
					'property',
					'property',
					'property',
					'property',
					'property',
					'property',
				];
				$title            = getTranslatedContent( $this->title ) . " | ";
				$description      = getTranslatedContent( $this->excerption );
				$meta_description = "";
				$img_url          = getFullUrlImgByKey($this->img_evidence_text);
				break;

			case( $collection_name == "course" ):
				$meta_content     = [ 'article' ];
				$meta_value       = [ 'og:type', ];
				$meta_key         = [ 'property', ];
				$title            = getTranslatedContent( $this->title ) . " | ";
				$description      = getTranslatedContent( $this->shortDescription );
				$meta_description = getTranslatedContent( $this->shortDescription );
				$img_url          = getFullUrlImgByKey($this->img_evidence_text);

				break;

			case( $collection_name == "event" ):
				$meta_content     = ['product'];
				$meta_value       = [];
				$meta_key         = [];
				$title            = getTranslatedContent( $this->title ) . " | ";
				$meta_description = getTranslatedContent( $this->shortDescription );
				$img_url          = getFullUrlImgByKey($this->img_evidence_text);

				break;

			case( $collection_name == "page" ):
				$meta_content     = [];
				$meta_value       = [];
				$meta_key         = [];
				$title            = getTranslatedContent( $this->title ) . " | ";
				$meta_description = getTranslatedContent($this->description);
				$img_url = "";
				break;

		}

		//common meta
		$obj_content = [
			$meta_description,
			env( 'APP_LOCALE' ),
			$title . getSiteGeneralValueByKey( 'company_name' ),
			$description,
			url()->current(),
			$img_url,
			$img_url,
			getSiteGeneralValueByKey( 'company_name' ),
			$fb_id,
			'@informaz',
			'@informaz',
			$title . getSiteGeneralValueByKey( 'company_name' ),
			$description,
			$img_url,
			'summary',
		];
		$obj_value   = [
			'description',
			'og:locale',
			'og:title',
			'og:description',
			'og:url',
			'og:image',
			'og:image:secure_url',
			'og:site_name',
			'fb:app_id',
			'twitter:creator',
			'twitter:site',
			'twitter:title',
			'twitter:description',
			'twitter:image',
			'twitter:card',
		];
		$obj_key     = [
			'name',
			'property',
			'property',
			'property',
			'property',
			'property',
			'property',
			'property',
			'property',
			'name',
			'name',
			'name',
			'name',
			'name',
			'name',
		];

		$obj_key     = array_merge( $obj_key, $meta_key );
		$obj_value   = array_merge( $obj_value, $meta_value );
		$obj_content = array_merge( $obj_content, $meta_content );

		for ( $i = 0; $i < count( $obj_key ); $i ++ ) {
			$obj = [
				'key'     => $obj_key[ $i ],
				'value'   => $obj_value[ $i ],
				'content' => $obj_content[ $i ],
			];
			//generate new sitegeneral to match obj_key number
			$meta[] = $obj;
		}

		return $meta;
	}



	/**
	 * @param int $numberOfRandomRow
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function getRandomRow( int $numberOfRandomRow = 0 ) {

		$totalRow = $this->count();
		if ( $numberOfRandomRow == 0 || $numberOfRandomRow < 0 ) {
			throw new Exception( "Invalid # of random record requested" );
		} else if ( $numberOfRandomRow > $totalRow ) {
			throw new Exception( "You have requested a number of record bigger than the count collection record ( " . $totalRow . ")" );
		} else if ( $numberOfRandomRow == 1 ) {
			return $this->skip( rand( 0, $totalRow - 1 ) )->take( $numberOfRandomRow )->first();
		} else {
			return $this->skip( rand( 0, $totalRow - 1 ) )->take( $numberOfRandomRow );
		}


	}

}