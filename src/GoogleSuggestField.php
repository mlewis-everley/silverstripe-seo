<?php

namespace Hubertusanton\SilverStripeSeo;

use SilverStripe\Forms\FormField;

class GoogleSuggestField extends FormField {

    public function Field($properties = array())
    {
        $this->addExtraClass('text');
        return parent::Field($properties);
    }
}
