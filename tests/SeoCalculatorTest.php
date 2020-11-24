<?php

namespace Hubertusanton\SilverStripeSeo\Tests;

use Hubertusanton\SilverStripeSeo\SeoCalculator;
use Page;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

class SeoCalculatorTests extends SapphireTest
{
    protected $usesDatabase = true;

    public function testGetThreshold()
    {
        $calc = SeoCalculator::create();
        $this->assertEquals(7, $calc->getThreshold());

        $calc->setSubject('Test');
        $this->assertEquals(6, $calc->getThreshold());
    }

    public function testGetRelevantScoreCriteria()
    {
        $calc = SeoCalculator::create();
        $criteria = $calc->getRelevantScoreCriteria();
        $this->assertArrayHasKey('numwords_content_ok', $criteria);
        $this->assertArrayHasKey('metatitle_length_ok', $criteria);
        $this->assertArrayHasKey('content_has_links', $criteria);
        $this->assertArrayHasKey('content_has_images', $criteria);
        $this->assertArrayHasKey('content_has_subtitles', $criteria);
        $this->assertArrayHasKey('images_have_alt_tags', $criteria);
        $this->assertArrayHasKey('images_have_title_tags', $criteria);
        $this->assertArrayNotHasKey('subject_defined', $criteria);
        $this->assertArrayNotHasKey('subject_in_title', $criteria);
        $this->assertArrayNotHasKey('subject_in_firstparagraph', $criteria);
        $this->assertArrayNotHasKey('subject_in_url', $criteria);
        $this->assertArrayNotHasKey('subject_in_metadescription', $criteria);
        $this->assertArrayNotHasKey('subject_in_image_alt_tags', $criteria);

        $calc->setSubject('Test');
        $criteria = $calc->getRelevantScoreCriteria();
        $this->assertArrayHasKey('subject_defined', $criteria);
        $this->assertArrayHasKey('subject_in_title', $criteria);
        $this->assertArrayHasKey('subject_in_firstparagraph', $criteria);
        $this->assertArrayHasKey('subject_in_url', $criteria);
        $this->assertArrayHasKey('subject_in_metadescription', $criteria);
        $this->assertArrayHasKey('subject_in_image_alt_tags', $criteria);
        $this->assertArrayNotHasKey('numwords_content_ok', $criteria);
        $this->assertArrayNotHasKey('metatitle_length_ok', $criteria);
        $this->assertArrayNotHasKey('content_has_links', $criteria);
        $this->assertArrayNotHasKey('content_has_images', $criteria);
        $this->assertArrayNotHasKey('content_has_subtitles', $criteria);
        $this->assertArrayNotHasKey('images_have_alt_tags', $criteria);
        $this->assertArrayNotHasKey('images_have_title_tags', $criteria);
    }

    public function testCheckSubjectDefined()
    {
        $calc = SeoCalculator::create();
        $this->assertFalse($calc->checkSubjectDefined());

        $calc->setSubject('test');
        $this->assertTrue($calc->checkSubjectDefined());
    }

    public function testCheckSubjectInTitle()
    {
        $calc = SeoCalculator::create();
        $this->assertFalse($calc->checkSubjectInTitle());

        $calc
            ->setSubject('test')
            ->setTitle('This is a title');
        $this->assertFalse($calc->checkSubjectInTitle());

        $calc->setTitle('This is a test title');
        $this->assertTrue($calc->checkSubjectInTitle());
    }

    public function testCheckSubjectInMetaDescription()
    {
        $calc = SeoCalculator::create();
        $this->assertFalse($calc->checkSubjectInMetaDescription());

        $calc
            ->setSubject('test')
            ->setMetaDescription('This is a description');
        $this->assertFalse($calc->checkSubjectInMetaDescription());

        $calc->setMetaDescription('This is a test description');
        $this->assertTrue($calc->checkSubjectInMetaDescription());
    }

    public function testCheckSubjectInContent()
    {
        $calc = SeoCalculator::create();
        $this->assertFalse($calc->checkSubjectInContent());

        $calc
            ->setSubject('test')
            ->setContent('<p>This is content to be checked</p>');
        $this->assertFalse($calc->checkSubjectInContent());

        $calc->setContent('<p>This is content to be checked with test in it</p>');
        $this->assertFalse($calc->checkSubjectInContent());

        $calc->setContent('<p>This is content to be checked with test in it</p><p>Another test sentence</p>');
        $this->assertTrue($calc->checkSubjectInContent());
    }

    public function testCheckSubjectInFirstParagraph()
    {
        $calc = SeoCalculator::create();
        $this->assertFalse($calc->checkSubjectInFirstParagraph());

        $calc
            ->setSubject('test')
            ->setContent('<p>This is content to be checked.</p>');
        $this->assertFalse($calc->checkSubjectInFirstParagraph());

        $calc->setContent(
            '<p>This is content to be checked.</p><p>Test is in the second paragraph.</p>'
        );
        $this->assertFalse($calc->checkSubjectInFirstParagraph());

        $calc->setContent(
            '<p>This is content to be checked with test in it</p><p>And a second paragraph with test</p>'
        );
        $this->assertTrue($calc->checkSubjectInFirstParagraph());
    }

    public function testCheckSubjectInUrl()
    {
        $calc = SeoCalculator::create();
        $this->assertFalse($calc->checkSubjectInUrl());

        $calc
            ->setSubject('test')
            ->setUrl('https://google.com');
        $this->assertFalse($calc->checkSubjectInUrl());

        $calc->setUrl('https://test.com');
        $this->assertTrue($calc->checkSubjectInUrl());

        $calc->setUrl('https://site.com/page/test');
        $this->assertTrue($calc->checkSubjectInUrl());

        $calc->setUrl('https://site.com/test/page');
        $this->assertTrue($calc->checkSubjectInUrl());

        $calc->setUrl('https://site.com/test');
        $this->assertTrue($calc->checkSubjectInUrl());
    }

    public function testCheckContentHasSubtitles()
    {
        $calc = SeoCalculator::create();

        $calc->setContent('<p>This is content to be checked.</p>');
        $this->assertFalse($calc->checkContentHasSubtitles());

        $calc->setContent(
            '<p>This is content to be checked.</p><p>Test is in the second paragraph.</p>'
        );
        $this->assertFalse($calc->checkContentHasSubtitles());

        $calc->setContent(
            '<p>This is content to be checked.</p><h2>This is a sub head</h2><p>And a second paragraph.</p>'
        );
        $this->assertTrue($calc->checkContentHasSubtitles());
    }

    public function testCheckContentHasLinks()
    {
        $calc = SeoCalculator::create();

        $calc->setContent('<p>This is content to be checked.</p>');
        $this->assertFalse($calc->checkContentHasLinks());

        $calc->setContent(
            '<p>This is content to be checked.</p><p>Test is in the second paragraph.</p>'
        );
        $this->assertFalse($calc->checkContentHasLinks());

        $calc->setContent(
            '<p>This is content to be checked.</p><h2>This is a sub head</h2><p>And a second paragraph.</p>'
        );
        $this->assertFalse($calc->checkContentHasLinks());

        $calc->setContent(
            '<p>This is content to be checked. <a href="https://google.com">Google</a></p><h2>This is a sub head</h2>'
        );
        $this->assertTrue($calc->checkContentHasLinks());

        $calc->setContent(
            '<p>This is content to be checked.</p><h2>This is a sub head <a href="https://google.com">Google</a></h2>'
        );
        $this->assertTrue($calc->checkContentHasLinks());
    }

    public function testCheckNumWordsContent()
    {
        $calc = SeoCalculator::create();

        $calc->setContent('<p>This is content to be checked.</p>');
        $this->assertFalse($calc->checkNumWordsContent());

        $calc->setContent(
            '<p>This is content to be checked.</p><p>Test is in the second paragraph.</p>'
        );
        $this->assertFalse($calc->checkNumWordsContent());

        $calc->setContent(
            '<p>This is content to be checked.</p><h2>This is a sub head</h2><p>And a second paragraph.</p>'
        );
        $this->assertFalse($calc->checkNumWordsContent());

        $calc->setContent(
            '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Bonum integritas corporis: misera debilitas.</p>
            <p>Quid autem habent admirationis, cum prope accesseris? Quoniam, inquiunt, omne peccatum inbecillitatis et inconstantiae est, haec autem vitia in omnibus stultis aeque magna sunt, necesse est paria esse peccata.
            <p>Duo Reges: constructio interrete. Verum enim diceret, idque Socratem, qui voluptatem nullo loco numerat, audio dicentem, cibi condimentum esse famem, potionis sitim.</p>
            <p>Aliam vero vim voluptatis esse, aliam nihil dolendi, nisi valde pertinax fueris, concedas necesse est. Haec non erant eius, qui innumerabilis mundos infinitasque regiones, quarum nulla esset ora, nulla extremitas, mente peragravisset.</p>
            <p>Tum Piso: Atqui, Cicero, inquit, ista studia, si ad imitandos summos viros spectant, ingeniosorum sunt; Praeterea sublata cognitione et scientia tollitur omnis ratio et vitae degendae et rerum gerendarum.</p>
            <p>Familiares nostros, credo, Sironem dicis et Philodemum, cum optimos viros, tum homines doctissimos. Sed ea mala virtuti magnitudine obruebantur. Quorum altera prosunt, nocent altera. Quem enim ardorem studii censetis fuisse in Archimede, qui dum in pulvere quaedam describit attentius, ne patriam quidem captam esse senserit?</p>
            <p>Modo etiam paulum ad dexteram de via declinavi, ut ad Pericli sepulcrum accederem. Eaedem enim utilitates poterunt eas labefactare atque pervertere. Illis videtur, qui illud non dubitant bonum dicere -; Atqui, inquit, si Stoicis concedis ut virtus sola, si adsit vitam efficiat beatam, concedis etiam Peripateticis.</p>
            <p>Ampulla enim sit necne sit, quis non iure optimo irrideatur, si laboret? Videsne quam sit magna dissensio? Ergo ita: non posse honeste vivi, nisi honeste vivatur? Cum enim summum bonum in voluptate ponat, negat infinito tempore aetatis voluptatem fieri maiorem quam finito atque modico.</p>
            <p>Modo etiam paulum ad dexteram de via declinavi, ut ad Pericli sepulcrum accederem.</p>'
        );
        $this->assertTrue($calc->checkNumWordsContent());
    }

    public function testCheckMetaTitleLength()
    {
        $calc = SeoCalculator::create();
        $config = SiteConfig::current_site_config();
        $curr = $config->Title;
        $config->Title = "";
        $config->write();

        $page = Page::create();
        $page->Title = "Test";
        $page->write();
        $page->doPublish();

        $calc->setUrl($page->AbsoluteLink());
        $this->assertFalse($calc->checkMetaTitleLength());

        $page->Title = "Lorem ipsum dolor sit amet, consectetur adipiscing elit";
        $page->write();
        $page->doPublish();
        $this->assertTrue($calc->checkMetaTitleLength());

        $page->Title = "Quem enim ardorem studii censetis fuisse in Archimede, qui dum in pulvere.";
        $page->write();
        $page->doPublish();
        $this->assertFalse($calc->checkMetaTitleLength());

        $config->Title = $curr;
        $config->write();
    }

    public function testCheckContentHasImages()
    {
        $calc = SeoCalculator::create();

        $calc->setContent('<p>This is content to be checked.</p>');
        $this->assertFalse($calc->checkContentHasImages());

        $calc->setContent(
            '<p>This is content to be checked.</p><p>Test is in the second paragraph.</p>'
        );
        $this->assertFalse($calc->checkContentHasImages());

        $calc->setContent(
            '<p>This is content to be checked.</p><h2>This is a sub head</h2><p>And a second paragraph.</p>'
        );
        $this->assertFalse($calc->checkContentHasImages());

        $calc->setContent(
            '<p>This is content to be checked. <a href="https://google.com">Google</a></p><h2>This is a sub head</h2>'
        );
        $this->assertFalse($calc->checkContentHasImages());

        $calc->setContent(
            '<p><img src="logo.png" /> This is content.</p>'
        );
        $this->assertTrue($calc->checkContentHasImages());

        $calc->setContent(
            '<p><img src="logo.png" /> This is content.</p><h2>This is a sub head</h2>'
        );
        $this->assertTrue($calc->checkContentHasImages());

        $calc->setContent(
            '<p><img src="logo.png" /> This is content.</p><h2>This is a sub head <a href="https://google.com">Google</a></h2>'
        );
        $this->assertTrue($calc->checkContentHasImages());
    }

    public function testCheckImageAltTags()
    {
        $calc = SeoCalculator::create();

        $calc->setContent('<p>This is content to be checked.</p>');
        $this->assertTrue($calc->checkImageAltTags());

        $calc->setContent(
            '<p>This is content to be checked.</p><p>Test is in the second paragraph.</p>'
        );
        $this->assertTrue($calc->checkImageAltTags());

        $calc->setContent(
            '<p>This is content to be checked.</p><h2>This is a sub head</h2><p>And a second paragraph.</p>'
        );
        $this->assertTrue($calc->checkImageAltTags());

        $calc->setContent(
            '<p><img src="logo.png" /> This is content.</p>'
        );
        $this->assertFalse($calc->checkImageAltTags());

        $calc->setContent(
            '<p><img src="logo.png" /> This is content.<img src="logo.png" alt="test"/></p>'
        );
        $this->assertFalse($calc->checkImageAltTags());

        $calc->setContent(
            '<p><img src="logo.png" alt="test"/> This is content.</p><h2>This is a sub head</h2>'
        );
        $this->assertTrue($calc->checkImageAltTags());

        $calc->setContent(
            '<p><img src="logo.png" alt="test"/> This is content.</p><h2><img src="logo.png" alt="test"/> This is a sub head</h2>'
        );
        $this->assertTrue($calc->checkImageAltTags());
    }

    public function testCheckSubjectInImageAltTags()
    {
        $calc = SeoCalculator::create();
        $calc->setSubject('Test');

        $calc->setContent('<p>This is content to be checked.</p>');
        $this->assertTrue($calc->checkSubjectInImageAltTags());

        $calc->setContent(
            '<p>This is content to be checked.</p><p>Test is in the second paragraph.</p>'
        );
        $this->assertTrue($calc->checkSubjectInImageAltTags());

        $calc->setContent(
            '<p><img src="logo.png" /> This is content.</p>'
        );
        $this->assertFalse($calc->checkSubjectInImageAltTags());

        $calc->setContent(
            '<p><img src="logo.png" /> This is content.<img src="logo.png" alt="test"/></p>'
        );
        $this->assertFalse($calc->checkSubjectInImageAltTags());

        $calc->setContent(
            '<p><img src="logo.png" alt="test"/> This is content.</p><h2>This is a sub head</h2>'
        );
        $this->assertTrue($calc->checkSubjectInImageAltTags());

        $calc->setContent(
            '<p><img src="logo.png" alt="test"/> This is content.</p><h2><img src="logo.png" alt="test"/> This is a sub head</h2>'
        );
        $this->assertTrue($calc->checkSubjectInImageAltTags());
    }

    public function testCheckImageTitleTags()
    {
        $calc = SeoCalculator::create();
        $calc->setSubject('Test');

        $calc->setContent('<p>This is content to be checked.</p>');
        $this->assertTrue($calc->checkImageTitleTags());

        $calc->setContent(
            '<p>This is content to be checked.</p><p>Test is in the second paragraph.</p>'
        );
        $this->assertTrue($calc->checkImageTitleTags());

        $calc->setContent(
            '<p><img src="logo.png" /> This is content.</p>'
        );
        $this->assertFalse($calc->checkImageTitleTags());

        $calc->setContent(
            '<p><img src="logo.png" title="Test"/> This is content.<img src="logo.png" alt="test"/></p>'
        );
        $this->assertFalse($calc->checkImageTitleTags());

        $calc->setContent(
            '<p><img src="logo.png" title="test"/> This is content.</p><h2>This is a sub head</h2>'
        );
        $this->assertTrue($calc->checkImageTitleTags());

        $calc->setContent(
            '<p><img src="logo.png" title="test"/> This is content.</p><h2><img src="logo.png" title="test"/> This is a sub head</h2>'
        );
        $this->assertTrue($calc->checkImageTitleTags());
    }

    public function testCalculateScoreAndPercentageGlobal()
    {
        $calc = SeoCalculator::create();
        $config = SiteConfig::current_site_config();
        $curr = $config->Title;
        $config->Title = "";
        $config->write();
        $content = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Bonum integritas corporis: misera debilitas.</p>
        <p>Quid autem habent admirationis, cum prope accesseris? Quoniam, inquiunt, omne peccatum inbecillitatis et inconstantiae est, haec autem vitia in omnibus stultis aeque magna sunt, necesse est paria esse peccata.
        <p>Duo Reges: constructio interrete. Verum enim diceret, idque Socratem, qui voluptatem nullo loco numerat, audio dicentem, cibi condimentum esse famem, potionis sitim.</p>
        <p>Aliam vero vim voluptatis esse, aliam nihil dolendi, nisi valde pertinax fueris, concedas necesse est. Haec non erant eius, qui innumerabilis mundos infinitasque regiones, quarum nulla esset ora, nulla extremitas, mente peragravisset.</p>
        <p>Tum Piso: Atqui, Cicero, inquit, ista studia, si ad imitandos summos viros spectant, ingeniosorum sunt; Praeterea sublata cognitione et scientia tollitur omnis ratio et vitae degendae et rerum gerendarum.</p>
        <p>Familiares nostros, credo, Sironem dicis et Philodemum, cum optimos viros, tum homines doctissimos. Sed ea mala virtuti magnitudine obruebantur. Quorum altera prosunt, nocent altera. Quem enim ardorem studii censetis fuisse in Archimede, qui dum in pulvere quaedam describit attentius, ne patriam quidem captam esse senserit?</p>
        <p>Modo etiam paulum ad dexteram de via declinavi, ut ad Pericli sepulcrum accederem. Eaedem enim utilitates poterunt eas labefactare atque pervertere. Illis videtur, qui illud non dubitant bonum dicere -; Atqui, inquit, si Stoicis concedis ut virtus sola, si adsit vitam efficiat beatam, concedis etiam Peripateticis.</p>
        <p>Ampulla enim sit necne sit, quis non iure optimo irrideatur, si laboret? Videsne quam sit magna dissensio? Ergo ita: non posse honeste vivi, nisi honeste vivatur? Cum enim summum bonum in voluptate ponat, negat infinito tempore aetatis voluptatem fieri maiorem quam finito atque modico.</p>
        <p>Modo etiam paulum ad dexteram de via declinavi, ut ad Pericli sepulcrum accederem.</p>';

        $this->assertEquals(0, $calc->calculateScore());
        $this->assertEquals(0, $calc->calculateScorePercentage());

        $calc->setContent($content);
        $this->assertEquals(3, $calc->calculateScore());
        $this->assertEquals(43, $calc->calculateScorePercentage());

        $page = Page::create();
        $page->Title = "Lorem ipsum corporis studii censetis fuisse in Archimede qui dum.";
        $page->write();
        $page->doPublish();

        $calc->setUrl($page->AbsoluteLink());
        $this->assertEquals(4, $calc->calculateScore());
        $this->assertEquals(57, $calc->calculateScorePercentage());

        $content .= '<a href="https://www.google.com">Google</a>';
        $calc->setContent($content);
        $this->assertEquals(5, $calc->calculateScore());
        $this->assertEquals(71, $calc->calculateScorePercentage());

        $content .= '<img src="logo.png" alt="test" title="test" />';
        $calc->setContent($content);
        $this->assertEquals(6, $calc->calculateScore());
        $this->assertEquals(86, $calc->calculateScorePercentage());

        $content .= '<h2>Test Subtitle</h2>';
        $calc->setContent($content);
        $this->assertEquals(7, $calc->calculateScore());
        $this->assertEquals(100, $calc->calculateScorePercentage());

        $config->Title = $curr;
        $config->write();
    }

    public function testCalculateScoreSubject()
    {
        $calc = SeoCalculator::create();
        $config = SiteConfig::current_site_config();
        $curr = $config->Title;
        $config->Title = "";
        $config->write();
        $content = '<p>Lorem ipsum dolor sit amet test, consectetur adipiscing elit. Bonum integritas corporis: misera debilitas.</p>
        <p>Quid autem habent admirationis, cum prope accesseris? Quoniam, inquiunt, omne peccatum inbecillitatis et inconstantiae est, haec autem vitia in omnibus stultis aeque magna sunt, necesse est paria esse peccata.
        <p>Duo Reges: constructio interrete. Verum enim diceret, idque Socratem, qui test voluptatem nullo loco numerat, audio dicentem, cibi condimentum esse famem, potionis sitim.</p>
        <p>Aliam vero vim voluptatis esse, aliam nihil dolendi, nisi valde pertinax fueris, concedas necesse est. Haec non erant eius, qui innumerabilis mundos infinitasque regiones, quarum nulla esset ora, nulla extremitas, mente peragravisset.</p>
        <p>Tum Piso: Atqui, Cicero, inquit, ista studia, si test ad imitandos summos viros spectant, ingeniosorum sunt; Praeterea sublata cognitione et scientia tollitur omnis ratio et vitae degendae et rerum gerendarum.</p>
        <p>Familiares nostros, credo, Sironem dicis et Philodemum, cum optimos viros, tum homines doctissimos. Sed ea mala virtuti magnitudine obruebantur. Quorum altera prosunt, nocent altera. Quem enim ardorem studii censetis fuisse in Archimede, qui dum in pulvere quaedam describit attentius, ne patriam quidem captam esse senserit?</p>
        <p>Modo etiam paulum ad dexteram de via declinavi, ut ad Pericli sepulcrum accederem. Eaedem enim utilitates poterunt eas labefactare atque pervertere. Illis videtur, qui illud non dubitant bonum dicere -; Atqui, inquit, si Stoicis concedis ut virtus sola, si adsit vitam efficiat beatam, concedis etiam Peripateticis.</p>
        <p>Ampulla enim sit necne sit, test quis non iure optimo irrideatur, si laboret? Videsne quam sit magna dissensio? Ergo ita: non posse honeste vivi, nisi honeste vivatur? Cum enim summum bonum in voluptate ponat, negat infinito tempore aetatis voluptatem fieri maiorem quam finito atque modico.</p>
        <p>Modo etiam paulum ad dexteram de via declinavi, ut ad Pericli sepulcrum accederem.</p>';

        $this->assertEquals(0, $calc->calculateScore());
        $this->assertEquals(0, $calc->calculateScorePercentage());

        $calc->setSubject('test');
        $this->assertEquals(1, $calc->calculateScore());
        $this->assertEquals(14, $calc->calculateScorePercentage());

        $calc->setTitle("This is a test");
        $this->assertEquals(2, $calc->calculateScore());
        $this->assertEquals(29, $calc->calculateScorePercentage());

        $calc->setContent($content);
        $this->assertEquals(5, $calc->calculateScore());
        $this->assertEquals(71, $calc->calculateScorePercentage());

        $page = Page::create();
        $page->Title = "Test Page";
        $page->write();
        $page->doPublish();

        $calc->setUrl($page->AbsoluteLink());
        $this->assertEquals(6, $calc->calculateScore());
        $this->assertEquals(86, $calc->calculateScorePercentage());

        $calc->setMetaDescription("A test meta description");
        $this->assertEquals(7, $calc->calculateScore());
        $this->assertEquals(100, $calc->calculateScorePercentage());

        $calc->setContent($content . '<img src="logo.png" />');
        $this->assertEquals(6, $calc->calculateScore());
        $this->assertEquals(86, $calc->calculateScorePercentage());

        $calc->setContent($content . '<img src="logo.png" alt="test" title="test" />');
        $this->assertEquals(7, $calc->calculateScore());
        $this->assertEquals(100, $calc->calculateScorePercentage());

        $config->Title = $curr;
        $config->write();
    }
}
