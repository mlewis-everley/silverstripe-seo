<?php

namespace Hubertusanton\SilverStripeSeo;

use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\LiteralField;
use Hubertusanton\SilverStripeSeo\GoogleSuggestField;
use SilverStripe\ORM\FieldType\DBHTMLText;

class SeoSubject extends DataObject
{
    private static $table_name = "SeoSubject";

    private static $db = [
        'SearchPhrase' => 'Varchar(256)'
    ];

    private static $has_one = [
        'Parent' => DataObject::class
    ];

    private static $casting = [
        'SEOScore' => 'Decimal',
        'TipsList' => 'Varchar'
    ];

    private static $field_labels = [
        'SEOScoreHTML' => 'SEO Rating',
        'TipsList' => 'Tips'
    ];

    private static $summary_fields = [
        'SEOScoreHTML',
        'SearchPhrase',
        'TipsList'
    ];

    /**
     * Currently active SEO Calculator
     *
     * @var SeoCalculator
     */
    protected $calculator;

    /**
     * Generate and cache an instance of SEO calculator for this subject
     */
    public function getSeoCalculator()
    {
        if (!empty($this->calculator)) {
            return $this->calculator;
        }

        $calc = SeoCalculator::create();
        $parent = $this->Parent();

        if (!empty($this->SearchPhrase) && !empty($parent)) {
            $calc
                ->setSubject($this->SearchPhrase)
                ->setTitle($parent->Title)
                ->setContent($parent->Content)
                ->setUrl($parent->AbsoluteLink())
                ->setMetaDescription($parent->MetaDescription);
        }

        $this->calculator = $calc;
        return $calc;
    }

    public function getSEOScore()
    {
        return $this->getSeoCalculator()->getScoreOutOfFive();
    }

    public function getSEOScoreHTML()
    {
        $html = DBHTMLText::create();
        $html->setValue($this->getSeoCalculator()->getHTMLStars());
        return $html;
    }

    public function getTipsList()
    {
        $tips = $this->getSeoCalculator()->getTipsArray();
        return implode(' ', $tips);
    }

    public function getCMSFields()
    {
        $self = $this;

        $this->beforeUpdateCMSFields(
            function ($fields) use ($self) {
                $calc = $this->getSeoCalculator();

                $fields->addFieldToTab(
                    'Root.Main',
                    GoogleSuggestField::create(
                        "SearchPhrase",
                        _t('SEO.SEOPageSubjectTitle', 'SEO Subject (required to view SEO score)')
                    )
                );

                if ($this->isInDB()) {
                    $fields->addFieldsToTab(
                        'Root.Main',
                        [
                            LiteralField::create('', '<div class="message notice"><p>' .
                                _t(
                                    'SEO.SEOSaveNotice',
                                    "After making changes save to view the updated SEO score"
                                ) . '</p></div>'),
                            LiteralField::create('ScoreTitle', '<h4 class="seo_score">' . _t('SEO.SEOScore', 'SEO Score') . '</h4>'),
                            LiteralField::create('Score', $calc->getHTMLStars()),
                            LiteralField::create('ScoreClear', '<div class="score_clear"></div>'),
                            LiteralField::create('ScoreTipsTitle', '<h4 class="seo_score">' . _t('SEO.SEOScoreTips', 'SEO Score Tips') . '</h4>'),
                            LiteralField::create('ScoreTips', $calc->getTipsAsHTML()),
                        ]
                    );
                }

            }
        );

        return parent::getCMSFields();
    }
}
