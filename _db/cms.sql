-- phpMyAdmin SQL Dump
-- version 4.6.4
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 30, 2017 at 11:58 AM
-- Server version: 10.1.19-MariaDB
-- PHP Version: 5.6.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `futureworks`
--

-- --------------------------------------------------------

--
-- Table structure for table `block`
--

CREATE TABLE `block` (
  `block_id` int(10) UNSIGNED NOT NULL,
  `page_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `show` int(10) UNSIGNED NOT NULL,
  `sort` int(10) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL,
  `panel_name` varchar(50) NOT NULL,
  `submenu_anchor` varchar(50) NOT NULL,
  `submenu_title` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `block`
--

INSERT INTO `block` (`block_id`, `page_id`, `parent_id`, `show`, `sort`, `title`, `panel_name`, `submenu_anchor`, `submenu_title`) VALUES
(1, 999999, 0, 0, 1, 'cms_settings', 'cms_settings', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `cms_file`
--

CREATE TABLE `cms_file` (
  `cms_file_id` int(10) UNSIGNED NOT NULL,
  `cms_user_id` int(10) UNSIGNED NOT NULL,
  `sort` int(10) UNSIGNED NOT NULL,
  `filename` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(100) NOT NULL,
  `title` varchar(500) NOT NULL,
  `date_posted` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_image`
--

CREATE TABLE `cms_image` (
  `cms_image_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `filename` varchar(100) NOT NULL,
  `hash` varchar(40) NOT NULL,
  `title` varchar(500) NOT NULL DEFAULT '',
  `description` varchar(500) NOT NULL DEFAULT '',
  `category` varchar(30) NOT NULL,
  `meta` mediumtext NOT NULL,
  `keyword` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `cms_image`
--

INSERT INTO `cms_image` (`cms_image_id`, `name`, `filename`, `hash`, `title`, `description`, `category`, `meta`, `keyword`) VALUES
(1, 'room_black_icon2', '2015/11/room_black_icon2.png', '', '', '', 'icon', '', ''),
(2, 'background', '2015/11/background.jpg', '', '', '', 'content', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `cms_keyword`
--

CREATE TABLE `cms_keyword` (
  `cms_keyword_id` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_page`
--

CREATE TABLE `cms_page` (
  `cms_page_id` int(11) UNSIGNED NOT NULL,
  `sort` int(11) UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `meta` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_page_panel_param`
--

CREATE TABLE `cms_page_panel_param` (
  `cms_page_panel_param_id` int(10) UNSIGNED NOT NULL,
  `cms_page_panel_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` mediumtext NOT NULL,
  `search` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `cms_page_panel_param`
--

INSERT INTO `cms_page_panel_param` (`cms_page_panel_param_id`, `cms_page_panel_id`, `name`, `value`, `search`) VALUES
(13, 1, 'favicon', '2015/11/room_black_icon2.png', 0),
(14, 1, 'cms_background', '2015/11/background.jpg', 0),
(15, 1, 'update_url', 'http://www.bytecrackers.com/cms/updater/', 0),
(16, 1, '', '{"favicon":"2015\\/11\\/room_black_icon2.png","cms_background":"2015\\/11\\/background.jpg","update_url":"http:\\/\\/www.bytecrackers.com\\/cms\\/updater\\/"}', 0);

-- --------------------------------------------------------

--
-- Table structure for table `cms_search_cache`
--

CREATE TABLE `cms_search_cache` (
  `term` varchar(30) NOT NULL,
  `cached_time` int(11) NOT NULL,
  `result` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_slug`
--

CREATE TABLE `cms_slug` (
  `cms_slug_id` varchar(100) NOT NULL,
  `target` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_text`
--

CREATE TABLE `cms_text` (
  `cms_text_id` varchar(50) NOT NULL,
  `text` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cms_user`
--

CREATE TABLE `cms_user` (
  `cms_user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `access` varchar(250) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `sort` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `menu_item`
--

CREATE TABLE `menu_item` (
  `menu_item_id` int(10) UNSIGNED NOT NULL,
  `menu_id` int(10) UNSIGNED NOT NULL,
  `sort` int(11) UNSIGNED NOT NULL,
  `mode` int(10) UNSIGNED NOT NULL,
  `link` varchar(100) NOT NULL,
  `text` varchar(100) NOT NULL,
  `new_window` int(10) UNSIGNED NOT NULL,
  `hide_from_menu` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `block`
--
ALTER TABLE `block`
  ADD PRIMARY KEY (`block_id`),
  ADD KEY `page_idx` (`page_id`);

--
-- Indexes for table `cms_file`
--
ALTER TABLE `cms_file`
  ADD PRIMARY KEY (`cms_file_id`),
  ADD KEY `user_id` (`cms_user_id`);

--
-- Indexes for table `cms_image`
--
ALTER TABLE `cms_image`
  ADD PRIMARY KEY (`cms_image_id`),
  ADD KEY `filename_idx` (`filename`(10)),
  ADD KEY `category_idx` (`category`(10)),
  ADD KEY `hash_idx` (`hash`);

--
-- Indexes for table `cms_keyword`
--
ALTER TABLE `cms_keyword`
  ADD PRIMARY KEY (`cms_keyword_id`);

--
-- Indexes for table `cms_page`
--
ALTER TABLE `cms_page`
  ADD PRIMARY KEY (`cms_page_id`),
  ADD KEY `slug_idx` (`slug`(4));

--
-- Indexes for table `cms_page_panel_param`
--
ALTER TABLE `cms_page_panel_param`
  ADD PRIMARY KEY (`cms_page_panel_param_id`),
  ADD UNIQUE KEY `cms_page_panel_idx` (`cms_page_panel_id`,`name`),
  ADD KEY `search_idx` (`search`),
  ADD KEY `value_idx` (`value`(10));

--
-- Indexes for table `cms_search_cache`
--
ALTER TABLE `cms_search_cache`
  ADD PRIMARY KEY (`term`);

--
-- Indexes for table `cms_slug`
--
ALTER TABLE `cms_slug`
  ADD PRIMARY KEY (`cms_slug_id`),
  ADD KEY `target_idx` (`target`(10));

--
-- Indexes for table `cms_text`
--
ALTER TABLE `cms_text`
  ADD KEY `cms_text_id` (`cms_text_id`);

--
-- Indexes for table `cms_user`
--
ALTER TABLE `cms_user`
  ADD PRIMARY KEY (`cms_user_id`);

--
-- Indexes for table `menu_item`
--
ALTER TABLE `menu_item`
  ADD PRIMARY KEY (`menu_item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `block`
--
ALTER TABLE `block`
  MODIFY `block_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table `cms_file`
--
ALTER TABLE `cms_file`
  MODIFY `cms_file_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `cms_image`
--
ALTER TABLE `cms_image`
  MODIFY `cms_image_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table `cms_page`
--
ALTER TABLE `cms_page`
  MODIFY `cms_page_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `cms_page_panel_param`
--
ALTER TABLE `cms_page_panel_param`
  MODIFY `cms_page_panel_param_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
--
-- AUTO_INCREMENT for table `cms_user`
--
ALTER TABLE `cms_user`
  MODIFY `cms_user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `menu_item`
--
ALTER TABLE `menu_item`
  MODIFY `menu_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
