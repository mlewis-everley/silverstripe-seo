<?php

namespace Hubertusanton\SilverStripeSeo;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Requirements;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\i18n\i18n;

/**
 * SeoObjectExtension extends SiteTree with functionality for helping content authors to
 * write good content for search engines, it uses the added var SEOPageSubject around
 * which the SEO score for the page is determined
 */
class SeoObjectExtension extends DataExtension
{
    use Configurable;

    /**
     * Specify page types that will not include the SEO tab
     *
     * @config
     * @var array
     */
    private static $excluded_page_types = [
        'ErrorPage',
        'RedirectorPage',
        'VirtualPage'
    ];

    /**
     * Return an array of Facebook Open Graph Types used in Meta tags
     *
     * @config
     * @var array
     **/
    private static $og_types = [
        'website' => 'Website',
        'article' => 'Article',
        'book'    => 'Book',
        'profile' => 'Profile',
        'music'   => 'Music',
        'video'   => 'Video'
    ];

    /**
     * Let the webmaster tag be edited by the CMS admin
     *
     * @config
     * @var boolean
     */
    private static $use_webmaster_tag = true;

    private static $db = [
        'SEOPageSubject' => 'Varchar(256)',
        'SEOSocialType' => 'Varchar',
        'SEOHideSocialData' => 'Boolean'
    ];

    private static $has_one = [
        'SEOSocialImage' => Image::class
    ];

    private static $has_many = [
        'SEOSubjects' => SeoSubject::class . '.Parent'
    ];

    private static $casting = [
        'SEOScore' => 'Decimal',
        'SEOSocialTitle' => 'Varchar',
        'SEOSocialLocale' => 'Varchar'
    ];

    protected $calculator;

    /**
     * Generate an instance of SEOCalculator for the parent
     *
     * @return SeoCalculator
     */
    public function getSeoCalculator()
    {
        if (!empty($this->calculator)) {
            return $this->calculator;
        }

        $owner = $this->getOwner();
        $calc = SeoCalculator::create()
            ->setTitle($owner->Title)
            ->setContent($owner->Content)
            ->setUrl($owner->AbsoluteLink())
            ->setMetaDescription($owner->MetaDescription);

        $this->calculator = $calc;
        return $calc;
    }

    /**
     * Calculate an SEO score based on the current owner and an average of all the subjects
     * (if any)
     *
     * return float 
     */
    public function getSEOScore()
    {
        $score = $this->getOwner()->getSeoCalculator()->getScoreOutOfFive();
        $subjects = $this->getOwner()->SEOSubjects();
        $subject_score = 0;

        if (!$subjects->exists()) {
            return $score;
        }

        foreach ($subjects as $subject) {
            $subject_score += $subject->getSeoCalculator()->getScoreOutOfFive();
        }

        $subject_score = $subject_score / $subjects->count();
        $score = ($score + $subject_score) / 2;
        return $score;
    }

    /**
     * Get star ratings as html (based on the currently calculated score)
     *
     * @return string
     */
    public function getHTMLStars()
    {
        $score = $this->getOwner()->getSEOScore();
        $num_stars = intval($score / 2);
        $num_nostars = 5 - $num_stars;
        $html = '<div id="fivestar-widget">';

        for ($i = 1; $i <= $num_stars; $i++) {
            $html .= '<div class="star on"></div>';
        }
        if ($score % 2) {
            $html .= '<div class="star on-half"></div>';
            $num_nostars--;
        }
        for ($i = 1; $i <= $num_nostars; $i++) {
            $html .= '<div class="star"></div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Update Silverstripe CMS Fields for SEO Module
     *
     * @param FieldList
     * @return none
     */
    public function updateCMSFields(FieldList $fields)
    {
        // exclude SEO tab from some pages
        $excluded = Config::inst()->get(self::class, 'excluded_page_types');
        $owner = $this->getOwner();

        if ($excluded) {
            if (in_array($this->owner->getClassName(), $excluded)) {
                return;
            }
        }

        // lets create a new tab on top
        $fields->addFieldsToTab(
            'Root.SEO',
            [
                LiteralField::create('googlesearchsnippetintro', '<h3>' . _t('SEO.SEOGoogleSearchPreviewTitle', 'Preview google search') . '</h3>'),
                LiteralField::create('googlesearchsnippet', '<div id="google_search_snippet"></div>'),
                LiteralField::create('siteconfigtitle', '<div id="ss_siteconfig_title">' . $this->owner->getSiteConfig()->Title . '</div>'),
            ]
        );

        // move Metadata field from Root.Main to SEO tab for visualising direct impact on search result
        $fields->removeFieldsFromTab(
            'Root.Main',
            [
                'Metadata',
                'SEOSocialType',
                'SEOHideSocialData',
                'SEOSocialImage',
                'SEOSubjects'
            ]
        );

        $fields->addFieldsToTab(
            'Root.SEO',
            [
                TextareaField::create("MetaDescription", $this->owner->fieldLabel('MetaDescription'))
                    ->setRightTitle(
                        _t(
                            'SiteTree.METADESCHELP',
                            "Search engines use this content for displaying search results (although it will not influence their ranking)."
                        )
                    )
                    ->addExtraClass('help'),
                LiteralField::create('', '<div class="message notice"><p>' .
                    _t(
                        'SEO.SEOSaveNotice',
                        "After making changes save to view the updated SEO score"
                    ) . '</p></div>'),
                LiteralField::create('ScoreTitle', '<h4 class="seo_score">' . _t('SEO.SEOScore', 'SEO Score') . '</h4>'),
                LiteralField::create('Score', $this->getHTMLStars()),
                LiteralField::create('ScoreClear', '<div class="score_clear"></div>'),
                LiteralField::create('ScoreTipsTitle', '<h4 class="seo_score">' . _t('SEO.SEOScoreTips', 'SEO Score Tips') . '</h4>'),
                LiteralField::create('ScoreTips', $owner->getSEOCalculator()->getTipsAsHTML())
            ]
        );

        // Add subjects
        $fields->addFieldToTab(
            'Root.SEO',
            ToggleCompositeField::create(
                'SEOSearchSubjects',
                _t('SEO.SEOSearchSubjects', "Search Subjects"),
                [
                    GridField::create('SEOSubjects', '', $owner->SEOSubjects())
                        ->setConfig(GridFieldConfig_RelationEditor::create())
                ]
            )
        );

        // Add Social settings
        $fields->addFieldToTab(
            'Root.SEO',
            ToggleCompositeField::create(
                'SEOSocialData',
                _t('SEO.SEOSocialData', "Social Data"),
                [
                    $this
                        ->getOwner()
                        ->dbObject('SEOHideSocialData')
                        ->scaffoldFormField()
                        ->setTitle(_t('SEO.SEOHideSocialDataDescription', 'Hide Social Data From Pages HTML?')),
                    DropdownField::create(
                        'SEOSocialType',
                        _t('SEO.SEOSocialType', 'Social Content Type')
                    )->setSource($this->config()->og_types),
                    UploadField::create(
                        'SEOSocialImage',
                        _t('SEO.SEOSocialImage', 'Image to share on Social Media')
                    )->setDescription(_t('SEO.SEODefaultImage', 'Defaults to featured image, if available'))
                ]
            )
        );
    }

    /**
     * Get the current title for this page (to load into social tags)
     * First try to get the MetaTitle (if the field is available), if
     * not, fall back to title
     *
     * @return string
     */
    public function getSEOSocialTitle()
    {
        // Try to use meta title field (if available)
        if (!empty($this->getOwner()->MetaTitle)) {
            return $this->getOwner()->MetaTitle;
        }

        return $this->getOwner()->Title;
    }

    /**
     * Get the current site locale.
     *
     * @return string
     */
    public function getSEOSocialLocale()
    {
        return i18n::get_locale();
    }

    /**
     * Attempt to find a suitable social image to use if one is not set.
     * By default try to see if this is a blog post and add the "Featured Image"
     *
     * @return Image 
     */
    public function getSEOPreferedSocialImage()
    {
        $owner = $this->getOwner();
        $social_image = $owner->SEOSocialImage();

        if (!$social_image->exists() && $owner->hasMethod('FeaturedImage') && $owner->FeaturedImage()->exists()) {
            return $owner->FeaturedImage();
        }

        // Return the default expected result
        return $social_image;
    }

    /**
     * Hooks into MetaTags SiteTree method and adds additional
     * meta data for Sharing of this page on Social Media
     *
     * @return null
     */
    public function MetaTags(&$tags)
    {
        $tags .= $this->getOwner()->renderWith('Hubertusanton\\SilverStripeSeo\\Includes\\SocialTags');

        if (Config::inst()->get('SeoObjectExtension', 'use_webmaster_tag')) {
            $siteConfig = SiteConfig::current_site_config();
            $tags .= $siteConfig->GoogleWebmasterMetaTag . "\n";
        }
    }

    /**
     * Return a breadcrumb trail to this page. Excludes "hidden" pages
     * (with ShowInMenus=0). Adds extra microdata compared to
     *
     * @param int $maxDepth The maximum depth to traverse.
     * @param boolean $unlinked Do not make page names links
     * @param string $stopAtPageType ClassName of a page to stop the upwards traversal.
     * @param boolean $showHidden Include pages marked with the attribute ShowInMenus = 0
     * @return string
     */
    public function SeoBreadcrumbs($separator = '&raquo;', $addhome = true, $maxDepth = 20, $unlinked = false, $stopAtPageType = false, $showHidden = false) {
        $page = $this->owner;
        $pages = array();

        while(
            $page
            && (!$maxDepth || count($pages) < $maxDepth)
            && (!$stopAtPageType || $page->ClassName != $stopAtPageType)
        ) {
            if($showHidden || $page->ShowInMenus || ($page->ID == $this->owner->ID)) {
                $pages[] = $page;
            }

            $page = $page->Parent;
        }
        // add homepage;
        if($addhome){
            $pages[] = SiteTree::get_by_link(RootURLController::get_homepage_link());
        }

        $template = new SSViewer('SeoBreadcrumbsTemplate');

        return $template->process($this->owner->customise(new ArrayData(array(
            'BreadcrumbSeparator' => $separator,
            'AddHome' => $addhome,
            'Pages' => new ArrayList(array_reverse($pages))
        ))));
    }

    /**
     * Get html of tips for the Page Subject
     *
     * @return string
     */
    public function getHTMLSimplePageSubjectTest() {
        return $this->owner->renderWith('SimplePageSubjectTest');
    }
}
