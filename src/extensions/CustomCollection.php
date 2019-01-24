<?php

namespace Extensions;

use App\Services\RightModuleLabelService;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use MongoDB\BSON\UTCDateTime;
use stdClass;

class CustomCollection extends Collection
{
    //Method to retrieve a collection by slug, very useful for frontend
    public function getCourseDetail($target_2 = null, $myslug = null)
    {
        $cl = cl();
        $out = $this->filter(function ($col) use ($target_2, $myslug, $cl) {
            if ($col->slug[$cl] == $myslug && $col->status == "published" && $col->visibility == "public" && $col->secondtarget->slug[$cl] == $target_2) {
                return true;
            }
        })->first();
        if (!$out) {//Handler 404 Object Not Found
            $obj_name = get_class($this->first());
            $message = __('error.' . $obj_name);
            abort(404, $message);
        } else {
            return $out;
        }

    }

    //Method to retrieve a collection by slug, very useful for frontend
    public function getBySlugAndStatus($category = null, $myslug = null)
    {
        $cl = cl();

        $out = $this->filter(function ($col) use ($category, $myslug, $cl) {
            if ($col->slug[$cl] == $myslug && $col->status == "published" && $col->primarycategory->slug[$cl] == $category) {
                return true;
            }
        })->first();
        if (!$out) {//Handler 404 Object Not Found
            $obj_name = get_class($this->first());
            $message = __('error.' . $obj_name);
            abort(404, $message);
        } else {
            return $out;
        }

    }


    public function getBySlug($myslug = null)
    {
        $cl = cl();
        $out = $this->filter(function ($col) use ($myslug, $cl) {
            if ($col->slug[$cl] == $myslug) {
                return true;
            }
        })->first();
        if (!$out) {//Handler 404 Object Not Found
            $obj_name = get_class($this->first());
            $message = __('error.' . $obj_name);
            abort(404, $message);
        } else {
            return $out;
        }

    }


    //Method to retrieve only not deleted item of a collection - Check on is_deleted custom property added on MDMODEL ovverriding init, delete
    public function getNotDeleted()
    {
        return $this->filter(function ($col) {
            if ($col->is_deleted) {
                return false;
            }
        });
    }

    //Method to retrieve only published item of a collection - Check on status entry
    public function getPublished()
    {
        return $this->filter(function ($col) {
            if ($col->status == "published") {
                return true;
            }
        });
    }

    //Method to retrieve only public item of a collection - Check on status entry
    public function getPublic()
    {
        return $this->filter(function ($col) {
            if ($col->visibility === "public") {
                return true;
            }
        });
    }


    //Method to retrieve only event that has date_start and hour >= now
    public function getNextEvents()
    {
        $now = new DateTime('now', new DateTimeZone('UTC'));


        return $this->filter(function ($col) use ($now) {
            $carbonDate = $col->date_time_start->toDateTime();
            //dd($carbonDate >= $now,$carbonDate,$now, $col->ref_id );

            return $carbonDate >= $now;

        })->sortBy('date_time_start');
    }

    //Check if the collection has an item with ref_id equal to id of the obj pass in to the parameter, useful to mark a category already selected in edit
    public function hasItem($obj)
    {
        if (is_null($obj)) {
            return false;
        } else if (is_null($obj->id)) {
            return false;
        }

        $id = $obj->id;

        $out = $this->filter(function ($col) use ($id) {

            if ($col->ref_id == $id) {

                return true;
            }
        });
        if ($out->count() > 0) {
            return true;
        } else {
            return false;
        }
    }

    //Move the item with ref_id equal to the parameter, useful for edit primary category
    public function moveFirst($id)
    {
        for ($i = 0; $i <= ($this->count() - 1); $i++) {
            $this[$i]->ref_id == $id ? $this->prepend($this->splice($i, 1)[0]) : 0;
        }

        return $this;

    }

    public function hasNotRole($role)
    {

        $this->filter(function ($role) {
            return !$this->hasRole($role);
        });

    }

    /**
     * @return mixed
     */
    public function getCoursesForLaici()
    {
        return $this->getCourseFilteredBySecondTarget('laico');
    }

    /**
     * @param $name
     *
     * @return CustomCollection
     */
    public function getCourseFilteredBySecondTarget($name)
    {

        $out = new CustomCollection();

        $this->each(function ($item) use ($name, $out) {
            $course_name = $item->getSecondTargetName();
            $hasMatch = $course_name == $name;

            if ($hasMatch) {
                $out->push($item);
            }
        });

        return $out;
    }

    public function getCourseFilteredByFirstTarget($name)
    {

        $out = new CustomCollection();

        $this->each(function ($item) use ($name, $out) {
            $course_name = $item->getFirstTargetName();
            $hasMatch = $course_name == $name;

            if ($hasMatch) {
                $out->push($item);
            }
        });

        return $out;
    }

    /**
     * @return mixed
     */
    public function getCoursesForSanitari()
    {
        return $this->getCourseFilteredBySecondTarget('sanitario');
    }

    public function getCourseForPersona()
    {
        return $this->getCourseFilteredByFirstTarget('persona');
    }

    public function getCourseForCompany()
    {
        return $this->getCourseFilteredByFirstTarget('azienda');
    }

    public function filterEmptyEmbedsMany($subCollection)
    {
        return $this->filter(function ($col) use ($subCollection) {
            $test = $col->$subCollection->count() > 0;
            return $test;
        });
    }

    /**
     * @param int $numberOfRandomRow
     *
     * @return mixed
     * @throws Exception
     */
    public function getRandomRow(int $numberOfRandomRow = 0)
    {

        $totalRow = $this->count();

        if ($numberOfRandomRow == 0 || $numberOfRandomRow < 0) {
            throw new Exception("Invalid # of random record requested");
        } else if ($numberOfRandomRow > $totalRow) {
            throw new Exception("You have requested a number of record bigger than the count collection record ( " . $totalRow . ")");
        } else {
            return $this->slice(rand(0, $totalRow - 1))->take($numberOfRandomRow);
        }


    }


    /**
     * @return false|string
     */
    public function getRightModuleQueryEvents($url_more_info_about)
    {
        $events = $this->getPublic()->getPublished();
        $arr = [];
        $arr['hasCompleteEvent'] = $events->hasEventType('complete');
        $arr['completeEventCounter'] = $events->countEventType('complete');
        $arr['hasRetrainingEvent'] = $events->hasEventType('retraining');
        $arr['retrainingEventCounter'] = $events->countEventType('retraining');
        $arr['url_more_info_about'] = route('frontendoa.contact.index', ['content' => $url_more_info_about]);

        $arr['total_counter'] = $arr['completeEventCounter'] + $arr['retrainingEventCounter'];
        //Complete Events
        if ($arr['hasCompleteEvent']) {
            $out = $events->getEventByTypeJson('complete', $url_more_info_about, $arr['completeEventCounter']);
            $arr['complete'] = $out[0];
            $arr['completeAvailableEventCounter'] = $out[1];
        } else {
            $arr['complete'] = [];
            $arr['completeAvailableEventCounter'] = 0;
        }

        //Retraining Events
        if ($arr['hasRetrainingEvent']) {
            $out = $events->getEventByTypeJson('retraining', $url_more_info_about, $arr['retrainingEventCounter']);
            $arr['retraining'] = $out[0];
            $arr['retrainingAvailableEventCounter'] = $out[1];
        } else {
            $arr['retraining'] = [];
            $arr['retrainingAvailableEventCounter'] = 0;
        }

        //dd($arr);
        $out = json_encode($arr);

        return $out;
    }


    /**
     * @param $url_more_info_about
     * @return array
     */
    public function getWidgetQueryEvents($url_more_info_about)
    {
        $events = $this->getNextEvents()->getPublic()->getPublished();
	    $app =  new RightModuleLabelService;
        $arr = [];
        foreach ($events->sortBy('date_time_start') as $event) {
            $obj = new stdClass;
            if ($event->is_sold_out) {
                $event->jotform = route('frontendoa.contact.index', ['content' => $url_more_info_about]);
            }
            $obj->autoincrement_id = $event->autoincrement_id;
            $obj->option_label = $event->date_start;
            $obj->option_label_with_remaining_tickets = $app->setStockTickets($event->ratio_tickets, $event->remaining_tickets, $event->available_tickets);
            $obj->stock_label = $app->setStockTickets($event->ratio_tickets, $event->remaining_tickets, $event->available_tickets);
            $obj->remaining_tickets = $event->remaining_tickets;
            $obj->available_tickets = $event->available_tickets;
            $obj->is_sold_out = $event->is_sold_out;
            $obj->price = formatPrice($event->total_price_general);
            $obj->event_url_jotform = $event->jotform;
            $obj->hour_start = $event->hour_start;
            $obj->eventprovince = $event->eventprovince;
            $obj->date_start = $event->date_start;
            $obj->slug = $event->slug;
            $obj->eventtype = $event->eventtype;
            $obj->total_price_general = $event->total_price_general;
            $obj->eventtype = $event->eventtype;
            $obj->jotform = $event->jotform;
            $obj->event_time = $event->hour_start . ' - ' . $event->hour_end;
            $obj->event_url = config('app.url') . "/" . getTranslatedContent($event->slug);
            $arr[] = $obj;
        }

        return $arr;
    }

    public function getEventByTypeJson($type, $url_more_info_about, $eventtype_counter)
    {
        $arr = [];
        if ($type !== "") {
            $eventsGrouped = $this->filter(function ($col) use ($type) {
                return $col->eventtype == $type;
            });
            $arr['eventtype_price'] = formatPriceNumeric($eventsGrouped->min('total_price_general'));
            $arr['eventtype_price_max'] = formatPriceNumeric($eventsGrouped->max('total_price_general'));


            if ($arr['eventtype_price'] == $arr['eventtype_price_max']) {
                $arr['min_max_price'] = formatPrice($eventsGrouped->min('total_price_general'));
            } else {
                $arr['min_max_price'] = $arr['eventtype_price'] . ' - ' . $arr['eventtype_price_max'] . ' €';

            }


            $arr['no_available_dates'] = !$eventsGrouped->hasEventAvailable();
            $count = $eventsGrouped->countEventAvailable();
            $eventsGrouped = $eventsGrouped->groupBy(['eventmunicipalityWithProvinceAcronym'])->sortKeys();

        } else {
            $arr['eventtype_price'] = formatPrice($this->min('total_price_general'));
            $arr['eventtype_price_max'] = formatPrice($this->max('total_price_general'));
            $eventsGrouped = $this->groupBy(['eventmunicipalityWithProvinceAcronym'])->sortKeys();
            $count = $eventsGrouped->countEventAvailable();
        }
	    $app =  new RightModuleLabelService;

        foreach ($eventsGrouped as $key => $events) {

            $obj = new stdClass;
            $obj->is_option_available = $events->hasEventAvailable();
            $obj->municipality_label = $events->setMunicipalityLabel($key, $eventtype_counter);
            $obj->municipality_id = str_slug($key);

            $dates = [];
            foreach ($events->sortBy('date_time_start') as $event) {
                if ($event->is_sold_out) {
                    $event->jotform = route('frontendoa.contact.index', ['content' => $url_more_info_about]);
                }
                $dates[] = [
                    'option_id' => $event->autoincrement_id,
                    'option_label' => $event->date_start,
                    'option_label_with_remaining_tickets' => $this->getDatesOptionLabelForRightModule($event->date_start, $event->remaining_tickets, $event->available_tickets, $event->ratio_tickets),
                    'stock_label' => $app->setStockTickets($event->ratio_tickets, $event->remaining_tickets, $event->available_tickets),
                    'remaining_tickets' => $event->remaining_tickets,
                    'available_tickets' => $event->available_tickets,
                    'is_sold_out' => $event->is_sold_out,
                    'price' => formatPrice($event->total_price_general),
                    'event_url_jotform' => $event->jotform,
                    'has_vat_general' => $this->setVatLabel($event->has_vat_general, $event->total_price_general),
                    'event_time' => $event->hour_start . ' - ' . $event->hour_end,
                    'event_url' => config('app.url') . "/" . getTranslatedContent($event->slug),
                    'deadline' => $app->setDeadline($event->deadline),
                    'duration' => $app->setDuration($event->duration),
                ]; //Array
            }
            $obj->dates = $dates;
            $obj->municipality_min_price = formatPriceNumeric($events->min('total_price_general'));
            $obj->municipality_max_price = formatPriceNumeric($events->max('total_price_general'));


            if ($obj->municipality_min_price == $obj->municipality_max_price) {
                $obj->min_max_price = formatPrice($events->min('total_price_general'));
            } else {
                $obj->min_max_price = $obj->municipality_min_price . ' - ' . $obj->municipality_max_price . ' €';

            }


            $arr[] = $obj;
        }

        return [$arr, $count];
    }


    public function getDatesOptionLabelForRightModule($dateMinified, $remainingTickets, $available_tickets, $ratio)
    {

        if ($available_tickets > 10) {
            if ($remainingTickets == 0) {
                return $dateMinified . " - [Esaurito]";

            } else if ($remainingTickets == 1) {
                return $dateMinified . " - [solo " . $remainingTickets . " posto rimasto]";

            } else if ($ratio <= 0.3 && $ratio > 0) {
                return $dateMinified . " - [solo " . $remainingTickets . " posti rimasti]";

            } else if ($ratio > 0.3 && $ratio <= 0.5) {
                return $dateMinified . " - [pochi posti rimasti]";

            } else {
                return $dateMinified;
            }

        } else {

            if ($remainingTickets == 0) {
                return $dateMinified . " - [Esaurito]";

            } else if ($remainingTickets == 1) {
                return $dateMinified . " - [solo " . $remainingTickets . " posto rimasto]";

            } else if ($remainingTickets <= 3 && $remainingTickets > 0) {
                return $dateMinified . " - [solo " . $remainingTickets . " posti rimasti]";

            } else if ($remainingTickets > 3 && $remainingTickets <= 5) {
                return $dateMinified . " - [pochi posti rimasti]";

            } else {
                return $dateMinified;
            }

        }

    }

    public function setVatLabel($has_vat, $price)
    {
        if ($price != 0) {
            if ($has_vat) {
                return __('views.frontend.vat');
            } else {
                return __('views.frontend.vat_excluded');
            }
        } else {
            return ' ';
        }
    }


    public function setMunicipalityLabel($key, $eventtype_counter)
    {
        $label = "";
        $count_available = $this->countEventAvailable();
        $count_soldout = $this->countEventSoldOut();
        if ($count_available == 1 && $eventtype_counter == 1) {
            return $key;
        } else if ($this->hasEventAvailable()) {

            if ($count_available == 1) {
                $label = __('views.frontend.pages.rightmodule.municipality_label1');
            } else {
                $label = __('views.frontend.pages.rightmodule.municipality_label');
            }

            $key = $key . ' - [' . $count_available . ' ' . $label . ']';
        } else {

            if ($count_soldout == 1) {
                $label = __('views.frontend.pages.rightmodule.municipality_label_no_date');
            } else {
                $label = __('views.frontend.pages.rightmodule.municipality_label_no_dates');
            }


            $key = $key . ' - [' . $count_soldout . $label . ']';
        }

        return $key;
    }

    /**
     * @param $type
     *
     * @return bool
     */
    public function hasEventType($type)
    {
        return $this->countEventType($type) > 0;
    }

    /**
     * @return bool
     */
    public function hasEventSoldOut()
    {
        return $this->countEventSoldOut() > 0;
    }

    /**
     * @return bool
     */
    public function hasEventAvailable()
    {
        return ($this->countEventAvailable() > 0);
    }

    /**
     * @return bool
     */
    public function hasMoreThanOneEventSoldOut()
    {
        return $this->countEventSoldOut() > 1;
    }

    /**
     * @return int
     */
    public function countEventSoldOut()
    {
        return $this->filter(function ($col) {
            return $col->is_sold_out;
        })->count();
    }

    /**
     * @return int
     */
    public function countEventAvailable()
    {
        return $this->filter(function ($col) {
            return !($col->is_sold_out);
        })->count();
    }

    /**
     * @param $type
     *
     * @return int
     */
    public function countEventType($type)
    {
        return $this->filter(function ($col) use ($type) {
            return $col->eventtype == $type;
        })->count();
    }

    /**
     * @return CustomCollection
     */
    public function getActive()
    {
        return $this->filter(function ($col) {
            return $col->is_active;
        });
    }

    /**
     * @return CustomCollection
     */
    public function getBetweenDates()
    {
        $now = new UTCDateTime(new DateTime('now'));

        return $this->filter(function ($col) use ($now) {
            return ($col->date_time_start <= $now) && ($col->date_time_end >= $now);
        });
    }

    public function exist()
    {
        if ($this->count() > 0) {
            return true;
        } else {
            return false;
        }
    }

	/**
	 * @param $url
	 * @param $impressions
	 */
	public function addOrSumImpressions($slug, $impressions, $uniqueImpressions){

		if(!is_null($this)){
			$match = $this->filter(function ($col) use ($slug){
				return $col['slug'] == $slug;
			});
			if($match->count() == 0){
				return $this->push(['slug' => $slug , 'impressions' => $impressions,'uniqueImpressions' => $uniqueImpressions]);
			}else{
				$impressions += $match->first()['impressions'];
				$uniqueImpressions += $match->first()['uniqueImpressions'];
				return $this->reject(function ($col) use ($slug) {
					return $col['slug'] == $slug;
				})->push(['slug' => $slug , 'impressions' => $impressions , 'uniqueImpressions' => $uniqueImpressions]);
			}
		}else{
			return $this->push(['slug' => $slug , 'impressions' => $impressions, 'uniqueImpressions' => $uniqueImpressions]);
		}
    }

	public function findByAID(String $aid)
	{
		return $this->filter(function ($col) use ($aid){
			return $col->autoincrement_id == $aid;
		})->first();
	}
}