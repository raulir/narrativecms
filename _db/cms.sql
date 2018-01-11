-- phpMyAdmin SQL Dump
-- version 4.1.6
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2017 at 08:42 PM
-- Server version: 5.6.16
-- PHP Version: 5.5.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `cms`
--

-- --------------------------------------------------------

--
-- Table structure for table `block`
--

CREATE TABLE IF NOT EXISTS `block` (
  `block_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `show` int(10) unsigned NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `panel_name` varchar(50) NOT NULL,
  `submenu_anchor` varchar(50) NOT NULL,
  `submenu_title` varchar(100) NOT NULL,
  PRIMARY KEY (`block_id`),
  KEY `page_idx` (`page_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `block`
--

INSERT INTO `block` (`block_id`, `page_id`, `parent_id`, `show`, `sort`, `title`, `panel_name`, `submenu_anchor`, `submenu_title`) VALUES
(1, 0, 0, 0, 1, '', 'cms_settings', '', ''),
(2, 0, 0, 0, 0, '', 'cms_cssjs_settings', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `cms_file`
--

CREATE TABLE IF NOT EXISTS `cms_file` (
  `cms_file_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cms_user_id` int(10) unsigned NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  `filename` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(100) NOT NULL,
  `title` varchar(500) NOT NULL,
  `date_posted` datetime NOT NULL,
  PRIMARY KEY (`cms_file_id`),
  KEY `user_id` (`cms_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `cms_image`
--

CREATE TABLE IF NOT EXISTS `cms_image` (
  `cms_image_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `filename` varchar(100) NOT NULL,
  `hash` varchar(40) NOT NULL,
  `title` varchar(500) NOT NULL DEFAULT '',
  `description` varchar(500) NOT NULL DEFAULT '',
  `category` varchar(30) NOT NULL,
  `meta` mediumtext NOT NULL,
  `keyword` varchar(200) NOT NULL,
  PRIMARY KEY (`cms_image_id`),
  KEY `filename_idx` (`filename`(10)),
  KEY `category_idx` (`category`(10)),
  KEY `hash_idx` (`hash`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=4 ;

--
-- Dumping data for table `cms_image`
--

INSERT INTO `cms_image` (`cms_image_id`, `name`, `filename`, `hash`, `title`, `description`, `category`, `meta`, `keyword`) VALUES
(2, 'background', '2015/11/background.jpg', '', '', '', 'content', '', ''),
(3, 'bytecrackers_logo_black', '2017/04/bytecrackers_logo_black.png', '', '', '', 'icon', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `cms_keyword`
--

CREATE TABLE IF NOT EXISTS `cms_keyword` (
  `cms_keyword_id` varchar(100) NOT NULL,
  PRIMARY KEY (`cms_keyword_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_page`
--

CREATE TABLE IF NOT EXISTS `cms_page` (
  `cms_page_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sort` int(11) unsigned NOT NULL,
  `slug` varchar(100) NOT NULL,
  `meta` mediumtext NOT NULL,
  PRIMARY KEY (`cms_page_id`),
  KEY `slug_idx` (`slug`(4))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `cms_page_panel_param`
--

CREATE TABLE IF NOT EXISTS `cms_page_panel_param` (
  `cms_page_panel_param_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cms_page_panel_id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` mediumtext NOT NULL,
  `search` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cms_page_panel_param_id`),
  UNIQUE KEY `cms_page_panel_idx` (`cms_page_panel_id`,`name`),
  KEY `search_idx` (`search`),
  KEY `value_idx` (`value`(10))
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=107 ;

--
-- Dumping data for table `cms_page_panel_param`
--

INSERT INTO `cms_page_panel_param` (`cms_page_panel_param_id`, `cms_page_panel_id`, `name`, `value`, `search`) VALUES
(42, 2, 'css.000', 'modules/cms/css/cms_normalise.scss', 0),
(43, 2, '', '{"css":{"000":"modules\\/cms\\/css\\/cms_normalise.scss"}}', 0),
(86, 1, 'favicon', '2017/04/bytecrackers_logo_black.png', 0),
(87, 1, 'cms_background', '2015/11/background.jpg', 0),
(88, 1, 'cms_update_url', 'http://cms.bytecrackers.com/cms/updater/', 0),
(89, 1, 'panel_cache', '0', 0),
(90, 1, 'images_rows', '4', 0),
(91, 1, 'images_lq_divider', '3', 0),
(92, 1, 'images_lq_width', '200', 0),
(93, 1, 'images_quality', '85', 0),
(94, 1, 'input_link_order', '0', 0),
(95, 1, 'rem_px', '1400', 0),
(96, 1, 'rem_ratio', '2', 0),
(97, 1, 'rem_m_px', '750', 0),
(98, 1, 'rem_switched', '0', 0),
(99, 1, 'rem_k', '100', 0),
(100, 1, 'rem_m_k', '50', 0),
(101, 1, 'images_pngquant', '0', 0),
(102, 1, 'images_pngquant_executable', '', 0),
(103, 1, 'images_zopflipng', '0', 0),
(104, 1, 'images_zopflipng_executable', '', 0),
(105, 1, 'deprecated', '0', 0),
(106, 1, '', '{"favicon":"2017\\/04\\/bytecrackers_logo_black.png","cms_background":"2015\\/11\\/background.jpg","cms_update_url":"http:\\/\\/www.bytecrackers.com\\/cms\\/cms\\/updater\\/","panel_cache":"0","images_rows":"4","images_lq_divider":"3","images_lq_width":"200","images_quality":"85","input_link_order":"0","rem_px":"1400","rem_ratio":"2","rem_m_px":"750","rem_switched":"0","rem_k":"100","rem_m_k":"50","images_pngquant":"0","images_pngquant_executable":"","images_zopflipng":"0","images_zopflipng_executable":"","deprecated":"0"}', 0);

-- --------------------------------------------------------

--
-- Table structure for table `cms_search_cache`
--

CREATE TABLE IF NOT EXISTS `cms_search_cache` (
  `term` varchar(30) NOT NULL,
  `cached_time` int(11) NOT NULL,
  `result` mediumtext NOT NULL,
  PRIMARY KEY (`term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_slug`
--

CREATE TABLE IF NOT EXISTS `cms_slug` (
  `cms_slug_id` varchar(100) NOT NULL,
  `target` varchar(100) NOT NULL,
  PRIMARY KEY (`cms_slug_id`),
  KEY `target_idx` (`target`(10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_text`
--

CREATE TABLE IF NOT EXISTS `cms_text` (
  `cms_text_id` varchar(50) NOT NULL,
  `text` mediumtext NOT NULL,
  KEY `cms_text_id` (`cms_text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_user`
--

CREATE TABLE IF NOT EXISTS `cms_user` (
  `cms_user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `access` varchar(250) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  PRIMARY KEY (`cms_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `menu_item`
--

CREATE TABLE IF NOT EXISTS `menu_item` (
  `menu_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` int(10) unsigned NOT NULL,
  `sort` int(11) unsigned NOT NULL,
  `mode` int(10) unsigned NOT NULL,
  `link` varchar(100) NOT NULL,
  `text` varchar(100) NOT NULL,
  `new_window` int(10) unsigned NOT NULL,
  `hide_from_menu` int(10) unsigned NOT NULL,
  PRIMARY KEY (`menu_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
