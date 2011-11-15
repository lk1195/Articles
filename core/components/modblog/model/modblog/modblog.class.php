<?php
/**
 * modBlog
 *
 * Copyright 2011-12 by Shaun McCormick <shaun+xvc@modx.com>
 *
 * modBlog is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * modBlog is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * modBlog; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package modblog
 */
require_once MODX_CORE_PATH.'model/modx/modprocessor.class.php';
require_once MODX_CORE_PATH.'model/modx/processors/resource/create.class.php';
require_once MODX_CORE_PATH.'model/modx/processors/resource/update.class.php';
/**
 * @package modblog
 */
class modBlog extends modResource {
    /**
     * Override modResource::__construct to ensure a few specific fields are forced to be set.
     * @param xPDO $xpdo
     */
    function __construct(& $xpdo) {
        parent :: __construct($xpdo);
        $this->set('class_key','modBlog');
        $this->set('hide_children_in_tree',true);
        $this->showInContextMenu = true;
    }

    /**
     * Get the controller path for our modBlog type.
     * 
     * {@inheritDoc}
     * @static
     * @param xPDO $modx
     * @return string
     */
    public static function getControllerPath(xPDO &$modx) {
        return $modx->getOption('modblog.core_path',null,$modx->getOption('core_path').'components/modblog/').'controllers/blog/';
    }

    /**
     * Provide the custom context menu for modBlog.
     * {@inheritDoc}
     * @return array
     */
    public function getContextMenuText() {
        $this->xpdo->lexicon->load('modblog:default');
        return array(
            'text_create' => $this->xpdo->lexicon('modblog.blog'),
            'text_create_here' => $this->xpdo->lexicon('modblog.blog_create_here'),
        );
    }

    /**
     * Provide the name of this CRT.
     * {@inheritDoc}
     * @return string
     */
    public function getResourceTypeName() {
        $this->xpdo->lexicon->load('modblog:default');
        return $this->xpdo->lexicon('modblog.blog');
    }

    /**
     * Override modResource::process to set some custom placeholders for the Resource when rendering it in the front-end.
     * {@inheritDoc}
     * @return string
     */
    public function process() {
        $this->xpdo->lexicon->load('modblog:frontend');
        $this->getPostListingCall();
        $this->getArchivistCall();
        $this->getTagListerCall();
        return parent::process();
    }

    /**
     * Get the getPage and getArchives call to display listings of posts on the blog.
     * @return void
     */
    public function getPostListingCall() {
        $settings = $this->getBlogSettings();

        $output = '[[!getPage?
          &elementClass=`modSnippet`
          &element=`getArchives`
          &cache=`0`
          &pageVarKey=`page`
          &parents=`[[*id]]`
          &where=`{"class_key":"modBlogPost"}`
          &limit=`'.$this->xpdo->getOption('postsPerPage',$settings,10).'`
          &showHidden=`1`
          &includeContent=`1`
          &includeTVs=`1`
          &tagKey=`modblogtags`
          &tagSearchType=`contains`
          &tpl=`'.$this->xpdo->getOption('tplPost',$settings,'modBlogPostRowTpl').'`
        ]]';
        $this->xpdo->setPlaceholder('posts',$output);

        $this->xpdo->setPlaceholder('paging','[[!+page.nav:notempty=`
<div class="paging">
<ul class="pageList">
  [[!+page.nav]]
</ul>
</div>
`]]');
    }

    /**
     * Get the Archivist call for displaying archives in month/year format on the blog.
     * @return void
     */
    public function getArchivistCall() {
        $settings = $this->getBlogSettings();
        $output = '[[!Archivist?
            &tpl=`'.$this->xpdo->getOption('tplArchiveMonth',$settings,'modBlogArchiveMonthTpl').'`
            &target=`'.$this->get('id').'`
            &parents=`'.$this->get('id').'`
            &depth=`4`
            &limit=`'.$this->xpdo->getOption('archiveListingsLimit',$settings,10).'`
            &useMonth=`'.$this->xpdo->getOption('archiveByMonth',$settings,1).'`
            &useFurls=`'.$this->xpdo->getOption('archiveWithFurls',$settings,1).'`
            &cls=`'.$this->xpdo->getOption('archiveCls',$settings,'').'`
            &altCls=`'.$this->xpdo->getOption('archiveAltCls',$settings,'').'`
            &setLocale=`1`
        ]]';
        $this->xpdo->setPlaceholder('archives',$output);
    }

    /**
     * Get the tagLister call for displaying tag listings on the front-end.
     * @return string
     */
    public function getTagListerCall() {
        $settings = $this->getBlogSettings();
        $output = '[[!tagLister?
            &tpl=`'.$this->xpdo->getOption('tplTagRow',$settings,'tag').'`
            &tv=`modblogtags`
            &parents=`'.$this->get('id').'`
            &tvDelimiter=`,`
            &limit=`'.$this->xpdo->getOption('tagsLimit',$settings,10).'`
            &cls=`'.$this->xpdo->getOption('tagsCls',$settings,'tl-tag').'`
            &altCls=`'.$this->xpdo->getOption('tagsAltCls',$settings,'tl-tag-alt').'`
            &target=`'.$this->get('id').'`
        ]]';
        $this->xpdo->setPlaceholder('tags',$output);
        return $output;
    }

    /**
     * Get an array of settings for the blog.
     * @return array
     */
    public function getBlogSettings() {
        $settings = $this->get('blog_settings');
        $this->xpdo->setDebug(false);
        if (!empty($settings)) {
            $settings = is_array($settings) ? $settings : $this->xpdo->fromJSON($settings);
        }
        return !empty($settings) ? $settings : array();
    }
}

/**
 * Overrides the modResourceCreateProcessor to provide custom processor functionality for the modBlog type
 *
 * @package modblog
 */
class modBlogCreateProcessor extends modResourceCreateProcessor {
    /**
     * Override modResourceCreateProcessor::afterSave to provide custom functionality, saving the blog settings to a
     * custom field in the manager
     * {@inheritDoc}
     * @return boolean
     */
    public function beforeSave() {
        $properties = $this->getProperties();
        $settings = $this->object->get('blog_settings');
        foreach ($properties as $k => $v) {
            if (substr($k,0,8) == 'setting_') {
                $key = substr($k,8);
                if ($v === 'false') $v = 0;
                if ($v === 'true') $v = 1;
                $settings[$key] = $v;
            }
        }
        $this->object->set('blog_settings',$settings);

        $this->object->set('cacheable',true);
        $this->object->set('isfolder',true);
        return parent::beforeSave();
    }

    /**
     * Override modResourceCreateProcessor::afterSave to provide custom functionality
     * {@inheritDoc}
     * @return boolean
     */
    public function afterSave() {
        $this->addBlogId();
        $this->setProperty('clearCache',true);
        return parent::afterSave();
    }

    /**
     * Add the Blog ID to the modblog system setting for managing blog IDs for FURL redirection.
     * @return boolean
     */
    public function addBlogId() {
        $saved = true;
        /** @var modSystemSetting $setting */
        $setting = $this->modx->getObject('modSystemSetting',array('key' => 'modblog.blog_ids'));
        if (!$setting) {
            $setting = $this->modx->newObject('modSystemSetting');
            $setting->set('key','modblog.blog_ids');
            $setting->set('namespace','modblog');
            $setting->set('area','furls');
            $setting->set('xtype','textfield');
        }
        $value = $setting->get('value');
        $archiveKey = $this->object->get('id').':arc_';
        $value = is_array($value) ? $value : explode(',',$value);
        if (!in_array($archiveKey,$value)) {
            $value[] = $archiveKey;
            $value = array_unique($value);
            $setting->set('value',implode(',',$value));
            $saved = $setting->save();
        }
        return $saved;
    }
}

/**
 * Overrides the modResourceUpdateProcessor to provide custom processor functionality for the modBlog type
 *
 * @package modblog
 */
class modBlogUpdateProcessor extends modResourceUpdateProcessor {
    /**
     * Override modResourceUpdateProcessor::beforeSave to provide custom functionality, saving settings for the blog
     * to a custom field in the DB
     * {@inheritDoc}
     * @return boolean
     */
    public function beforeSave() {
        $properties = $this->getProperties();
        $settings = $this->object->get('blog_settings');
        foreach ($properties as $k => $v) {
            if (substr($k,0,8) == 'setting_') {
                $key = substr($k,8);
                if ($v === 'false') $v = 0;
                if ($v === 'true') $v = 1;
                $settings[$key] = $v;
            }
        }
        $this->object->set('blog_settings',$settings);
        return parent::beforeSave();
    }

    /**
     * Override modResourceUpdateProcessor::afterSave to provide custom functionality
     * {@inheritDoc}
     * @return boolean
     */
    public function afterSave() {
        $this->addBlogId();
        $this->setProperty('clearCache',true);
        $this->object->set('isfolder',true);
        return parent::afterSave();
    }

    /**
     * Add the Blog ID to the modblog system setting for managing blog IDs for FURL redirection.
     * @return boolean
     */
    public function addBlogId() {
        $saved = true;
        /** @var modSystemSetting $setting */
        $setting = $this->modx->getObject('modSystemSetting',array('key' => 'modblog.blog_ids'));
        if (!$setting) {
            $setting = $this->modx->newObject('modSystemSetting');
            $setting->set('key','modblog.blog_ids');
            $setting->set('namespace','modblog');
            $setting->set('area','furls');
            $setting->set('xtype','textfield');
        }
        $value = $setting->get('value');
        $archiveKey = $this->object->get('id').':arc_';
        $value = is_array($value) ? $value : explode(',',$value);
        if (!in_array($archiveKey,$value)) {
            $value[] = $archiveKey;
            $value = array_unique($value);
            $setting->set('value',implode(',',$value));
            $saved = $setting->save();
        }
        return $saved;
    }
}