<?php

namespace Hubertusanton\SilverStripeSeo;

use DOMDocument;
use Exception;
use LogicException;
use SilverStripe\Core\Convert;
use SilverStripe\Control\Director;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Parsers\URLSegmentFilter;

class SeoCalculator
{
    use Configurable, Injectable;

    const METATITLE_MIN_LENGTH = 10;

    const METATITLE_MAX_LENGTH = 70;

    const CONTENT_MIN_LENGTH = 250;

    /**
     * List of scoring criteria for if a search subject is defined
     *
     * @var array
     */
    protected $score_criteria_subject = [
        'subject_defined' => false,
        'subject_in_title' => false,
        'subject_in_firstparagraph' => false,
        'subject_in_url' => false,
        'subject_in_metadescription' => false,
        'subject_in_image_alt_tags' => false, 
    ];

    /**
     * List of scoring "global" scoring criteria that is not dedpendent on a subject
     *
     * @var array
     */
    protected $score_criteria_global = [
        'numwords_content_ok' => false,
        'metatitle_length_ok' => false,
        'content_has_links' => false,
        'content_has_images' => false,
        'content_has_subtitles' => false,
        'images_have_alt_tags' => false,
        'images_have_title_tags' => false,
    ];

    /**
     * SEO subject that the provided content needs to be scored against
     *
     * @var string
     */
    protected $subject;

    /**
     * Title of the object that needs to be checked
     *
     * @var string
     */
    protected $title;

    /**
     * HTML String that can be parsed into a DOMDocument
     *
     * @var string 
     */
    protected $content;

    /**
     * URL that needs to be checked against
     *
     * @var string
     */
    protected $url;

    /**
     * Meta description of the object that needs to be checked
     *
     * @var string
     */
    protected $meta_description;

    /**
     * Rendered page HTML (cached to avoid making multiple requests)
     *
     * @var string
     */
    protected $rendered_html = "";

    /**
     * Perform multiple checks (depending on if a subject has been set or not) and returns a tally
     * of successful checks (which can then be converted into a percentage)
     *
     * @return int
     */
    public function calculateScore()
    {
        $subject = $this->getSubject();
        $totals = [];

        if (!empty($subject)) {
            $this->score_criteria_subject['subject_defined'] = $totals[] = $this->checkSubjectDefined();
            $this->score_criteria_subject['subject_in_title'] = $totals[] = $this->checkSubjectInTitle();
            $this->score_criteria_subject['subject_in_content'] = $totals[] = $this->checkSubjectInContent();
            $this->score_criteria_subject['subject_in_firstparagraph'] = $totals[] = $this->checkSubjectInFirstParagraph();
            $this->score_criteria_subject['subject_in_url'] = $totals[] = $this->checkSubjectInUrl();
            $this->score_criteria_subject['subject_in_metadescription'] = $totals[] = $this->checkSubjectInMetaDescription();
            $this->score_criteria_subject['subject_in_image_alt_tags'] = $totals[] = $this->checkSubjectInImageAltTags();
        } else {
            $this->score_criteria_global['numwords_content_ok'] = $totals[] = $this->checkNumWordsContent();
            $this->score_criteria_global['metatitle_length_ok'] = $totals[] = $this->checkMetaTitleLength();
            $this->score_criteria_global['content_has_links'] = $totals[] = $this->checkContentHasLinks();
            $this->score_criteria_global['content_has_images'] = $totals[] = $this->checkContentHasImages();
            $this->score_criteria_global['content_has_subtitles'] = $totals[] = $this->checkContentHasSubtitles();
            $this->score_criteria_global['images_have_alt_tags'] = $totals[] = $this->checkImageAltTags();
            $this->score_criteria_global['images_have_title_tags'] = $totals[] = $this->checkImageTitleTags();
        }

        return intval(array_sum($totals));
    }

    /**
     * Calculate the score as a percentage of the threshold
     *
     * @return float
     */
    public function calculateScorePercentage()
    {
        $score = $this->calculateScore();
        $threshold = $this->getThreshold();

        return round(($score / $threshold) * 100);
    }

    public function getScoreOutOfFive()
    {
        $score = $this->calculateScorePercentage() / 10;
        return $score / 2;
    }

    /**
     * Get star ratings as html (based on the currently calculated score)
     *
     * @return string
     */
    public function getHTMLStars()
    {
        $score = $this->calculateScorePercentage() / 10;
        $num_stars = intval($this->getScoreOutOfFive());
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
     * Calculate the score threshold
     *
     * @return int
     */
    public function getThreshold()
    {
        return count($this->getRelevantScoreCriteria());
    }

    /**
     * Get array of tips translated in current locale
     *
     * @return array
     */
    public function getTranslatedTips()
    {
        return [
            'subject_defined' => _t('SEO.SEOScoreTipPageSubjectDefined', 'Subject is not defined'),
            'subject_in_title' => _t('SEO.SEOScoreTipPageSubjectInTitle', 'Subject is not in the title'),
            'subject_in_firstparagraph' => _t('SEO.SEOScoreTipPageSubjectInFirstParagraph', 'Subject is not present in the first paragraph of the content of this page'),
            'subject_in_url' => _t('SEO.SEOScoreTipPageSubjectInURL', 'Subject is not present in the URL'),
            'subject_in_metadescription' => _t('SEO.SEOScoreTipPageSubjectInMetaDescription', 'Subject is not present in the meta description of the page'),
            'subject_in_image_alt_tags' => _t('SEO.SEOScoreTipSubjectInImageAltTags', 'Subject is not present in image alt tags'),
            'numwords_content_ok' => _t('SEO.SEOScoreTipNumwordsContentOk', 'The content of this page is too short and does not have enough words. Please create content of at least 300 words based on the Page subject.'),
            'metatitle_length_ok' => _t('SEO.SEOScoreTipPageTitleLengthOk', 'The metatitle of the page should have a length of between 10 and 70 characters.'),
            'content_has_links' => _t('SEO.SEOScoreTipContentHasLinks', 'The content of this page does not have any (outgoing) links.'),
            'content_has_images' => _t('SEO.SEOScoreTipPageHasImages', 'The content of this page does not have any images.'),
            'content_has_subtitles' => _t('SEO.SEOScoreTipContentHasSubtitles', 'The content of this page does not have any subtitles'),
            'images_have_alt_tags' => _t('SEO.SEOScoreTipImagesHaveAltTags', 'All images on this page do not have alt tags'),
            'images_have_title_tags' => _t('SEO.SEOScoreTipImagesHaveTitleTags', 'All images on this page do not have title tags')
        ];
    }

    /**
     * Get relevent score criteria (depending on if subject is set)
     *
     * @return array
     */
    public function getRelevantScoreCriteria()
    {
        if (!empty($this->getSubject())) {
            return $this->score_criteria_subject;
        }

        return $this->score_criteria_global;
    }

    /**
     * Get a list of tips based on calculated score
     *
     * @return array
     */
    public function getTipsArray()
    {
        $this->calculateScore();
        $translations = $this->getTranslatedTips();
        $tips = [];

        foreach ($this->getRelevantScoreCriteria() as $index => $crit) {
            if (!$crit && in_array($index, array_keys($translations))) {
                $tips[] = $translations[$index];
            }
        }

        return $tips;
    }

    /**
     * Get a list of tips as HTML
     *
     * @return string
     */
    public function getTipsAsHTML()
    {
        $tips = $this->getTipsArray();
        $html = "";

        if (count($tips) > 0) {
            $html .= '<ul id="seo_score_tips">';

            foreach ($tips as $text) {
                $html .= '<li>' . $text . '</li>';
            }

            $html .= '</ul>';
        }

        return $html;
    }

    /**
     * Checks if SEOSubject is defined
     *
     * @return bool
     */
    public function checkSubjectDefined()
    {
        return (trim($this->getSubject() != '')) ? true : false;
    }

    /**
     * Checks if defined PageSubject is present in the Page Title
     *
     * @return bool
     */
    public function checkSubjectInTitle()
    {
        if (!$this->checkSubjectDefined()) {
            return false;
        }
        
        if (preg_match('/' . preg_quote($this->getSubject(), '/') . '/i', $this->getTitle())) {
            return true;
        }

        return false;
    }

    /**
     * Checks if defined Subject is present in the provided MetaDescription
     *
     * @return bool
     */
    public function checkSubjectInMetaDescription()
    {
        if (!$this->checkSubjectDefined()) {
            return false;
        }

        if (preg_match('/' . preg_quote($this->getSubject(), '/') . '/i', $this->getMetaDescription())) {
            return true;
        }

        return false;
    }

    /**
     * Checks if defined eSubject is present in the provided Content
     *
     * @return bool
     */
    public function checkSubjectInContent()
    {
        if (!$this->checkSubjectDefined()) {
            return false;
        }

        // Strip first paragraph out of content (as this is checkedd elsewhere)
        $content = $this->getContent();
        $html = DBHTMLText::create()->setValue($content);
        $first_paragraph = $html->FirstParagraph();
        $content = str_replace($first_paragraph, "", $content);

        if (preg_match('/' . preg_quote($this->getSubject(), '/') . '/i', $content)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if defined Subject is present in the Content's First Paragraph
     *
     * @return bool
     */
    public function checkSubjectInFirstParagraph()
    {
        if (!$this->checkSubjectDefined()) {
            return false;
        }

        $html = DBHTMLText::create()->setValue($this->getContent());
        $first_paragraph = $html->FirstParagraph();

        if (trim($first_paragraph != '')
            && preg_match('/' . preg_quote($this->getSubject(), '/') . '/i', $first_paragraph)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Checks if defined Subject is present in the URL
     *
     * @return bool
     */
    public function checkSubjectInUrl()
    {
        if (!$this->checkSubjectDefined()) {
            return false;
        }

        $url = $this->getUrl();
        $subject_segment = $this->generateURLSegment($this->getSubject());

        if (preg_match('/' . preg_quote($subject_segment, '/') . '/i', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Check if page Content has a h2's in it
     *
     * @return bool
     */
    public function checkContentHasSubtitles()
    {
        try {
            $dom = $this->createDOMDocumentFromHTML($this->getContent());
            $elements = $dom->getElementsByTagName('h2');
            return ($elements->length) ? true : false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if page Content has a href's in it
     *
     * @return boolean
     */
    public function checkContentHasLinks()
    {
        try {
            $dom = $this->createDOMDocumentFromHTML($this->getContent());
            $elements = $dom->getElementsByTagName('a');
            return ($elements->length) ? true : false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Checks if the number of words in the Content is over the defined minimum
     *
     * @return boolean
     */
    public function checkNumWordsContent()
    {
        return ($this->countNumWordsContent() > self::CONTENT_MIN_LENGTH) ? true : false;
    }

    /**
     * Check if length of the rendered MetaTitle is within prefered range
     *
     * @return bool
     */
    public function checkMetaTitleLength()
    {
        try {
            $min = self::METATITLE_MIN_LENGTH;
            $max = self::METATITLE_MAX_LENGTH;
            $content = $this->getRenderedContent();
            $dom = $this->createDOMDocumentFromHTML($content);
            $title = $dom->getElementsByTagName('title')->item(0);

            if (!empty($title) && strlen($title->textContent) >= $min && strlen($title->textContent) <= $max) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Check if content has a image elements present
     *
     * @return boolean
     */
    public function checkContentHasImages()
    {
        try {
            $dom = $this->createDOMDocumentFromHTML($this->getContent());
            $elements = $dom->getElementsByTagName('img');
            return ($elements->length) ? true : false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Checks if images in content have alt tags
     *
     * @return boolean
     */
    public function checkImageAltTags()
    {
        try {
            $dom = $this->createDOMDocumentFromHTML($this->getContent());
            $images = $dom->getElementsByTagName('img');
            $images_with_alt_tags = 0;

            foreach ($images as $image){
                if ($image->hasAttribute('alt') && $image->getAttribute('alt') != ''){
                    $images_with_alt_tags++;
                }
            }

            if ($images_with_alt_tags == $images->length) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Checks if image alt tags contain page subject
     *
     * @return boolean
     */
    public function checkSubjectInImageAltTags()
    {
        try {
            $dom = $this->createDOMDocumentFromHTML($this->getContent());
            $images = $dom->getElementsByTagName('img');
            $subject = $this->getSubject();
            $images_with_subject = 0;

            foreach ($images as $image) {
                if ($image->hasAttribute('alt') && $image->getAttribute('alt') != '') {
                    if (preg_match('/' . preg_quote($subject, '/') . '/i', $image->getAttribute('alt'))) {
                        $images_with_subject++;
                    }
                }
            }

            if ($images_with_subject == $images->length) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Checks if images in content have title tags
     *
     * @param none
     * @return boolean
     */
    public function checkImageTitleTags()
    {
        try {
            $dom = $this->createDOMDocumentFromHTML($this->getRenderedContent());
            $images = $dom->getElementsByTagName('img');
            $images_with_title_tags = 0;

            foreach ($images as $image) {
                if ($image->hasAttribute('title') && $image->getAttribute('title') != '') {
                    $images_with_title_tags++;
                }
            }

            if ($images_with_title_tags == $images->length) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Generate a DomDocument object from the set HTML
     *
     * @param string $content
     * 
     * @throws LogicException
     *
     * @return DOMDocument Object
     */
    protected function createDOMDocumentFromHTML(string $content = null)
    {
        if (empty($content)) {
            $content = $this->getContent();
        }

        if (empty($content)) {
            throw new LogicException("No HTML content string set before conversion to DOM Object");
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML($content);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $dom;
    }

    /**
     * Get the number of words in the Page Content
     *
     * @return int
     */
    protected function countNumWordsContent()
    {
        return str_word_count((Convert::xml2raw($this->getContent())));
    }

    /**
     * Generate a valid URL segment based on the provded string
     *
     * @param string $title
     *
     * @return string
     */
    protected function generateURLSegment($title)
    {
        $filter = URLSegmentFilter::create();
        $filtered_title = $filter->filter($title);
        return $filtered_title;
    }

    /**
     * Get SEO subject
     *
     * @return string
     */ 
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set SEO subject
     *
     * @param string $subject
     *
     * @return self
     */ 
    public function setSubject(string $subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Get html content of the URL which SEO score is based on
     * (we use the same info as gets back from $Layout in template)
     *
     * @return string
     */
    public function getRenderedContent()
    {
        if (!empty($this->getUrl())) {
            $response = Director::test($this->getUrl());

            if (!$response->isError()) {
                $this->rendered_html = $response->getBody();
            }
        }

        return $this->rendered_html;
    }

    /**
     * Get HTML String that can be passed into a DOMDocument
     *
     * @return string
     */ 
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set HTML String that can be passed into a DOMDocument
     *
     * @param string $content
     *
     * @return self
     */ 
    public function setContent(string $content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get title of the object that needs to be checked
     *
     * @return string
     */ 
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set title of the object that needs to be checked
     *
     * @param string $title
     *
     * @return self
     */ 
    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get URL that needs to be checked against
     *
     * @return  string
     */ 
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set uRL that needs to be checked against
     *
     * @param string $url
     *
     * @return self
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get meta description of the object that needs to be checked
     *
     * @return  string
     */ 
    public function getMetaDescription()
    {
        return $this->meta_description;
    }

    /**
     * Set meta description of the object that needs to be checked
     *
     * @param string $description
     *
     * @return self
     */ 
    public function setMetaDescription(string $description)
    {
        $this->meta_description = $description;
        return $this;
    }
}
