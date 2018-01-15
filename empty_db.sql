-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 05. Jan 2018 um 16:11
-- Server-Version: 10.1.28-MariaDB
-- PHP-Version: 7.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `bzoqdpus_socialevent`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `activities`
--

CREATE TABLE IF NOT EXISTS `activities` (
  `activityid` bigint(20) NOT NULL AUTO_INCREMENT,
  `eventid` bigint(20) DEFAULT NULL,
  `offerid` bigint(20) DEFAULT NULL,
  `provider` varchar(64) NOT NULL,
  `title` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `start` bigint(20) NOT NULL,
  `venue` varchar(64) NOT NULL,
  `street` varchar(64) NOT NULL,
  `place` varchar(64) NOT NULL,
  `pictures` longblob NOT NULL,
  PRIMARY KEY (`activityid`),
  UNIQUE KEY `activityid` (`activityid`),
  KEY `activityid_2` (`activityid`),
  KEY `eventid` (`eventid`),
  KEY `offerid` (`offerid`),
  KEY `offerid_2` (`offerid`),
  KEY `provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- RELATIONEN DER TABELLE `activities`:
--   `offerid`
--       `offers` -> `offerid`
--   `eventid`
--       `events` -> `eventid`
--   `provider`
--       `users` -> `mail`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `events`
--

CREATE TABLE IF NOT EXISTS `events` (
  `eventid` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `provider` varchar(64) NOT NULL,
  `start` bigint(20) NOT NULL,
  `end` bigint(20) NOT NULL,
  `pictures` longblob NOT NULL,
  PRIMARY KEY (`eventid`),
  UNIQUE KEY `eventid` (`eventid`),
  KEY `provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- RELATIONEN DER TABELLE `events`:
--   `provider`
--       `users` -> `mail`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `offers`
--

CREATE TABLE IF NOT EXISTS `offers` (
  `offerid` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `provider` varchar(64) NOT NULL,
  `openinghours` text NOT NULL,
  `pictures` longblob NOT NULL,
  PRIMARY KEY (`offerid`),
  UNIQUE KEY `offerid` (`offerid`),
  KEY `provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- RELATIONEN DER TABELLE `offers`:
--   `provider`
--       `users` -> `mail`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `participations`
--

CREATE TABLE IF NOT EXISTS `participations` (
  `activityid` bigint(20) NOT NULL,
  `participant` varchar(64) NOT NULL,
  KEY `activityid` (`activityid`),
  KEY `participant` (`participant`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- RELATIONEN DER TABELLE `participations`:
--   `participant`
--       `users` -> `mail`
--   `activityid`
--       `activities` -> `activityid`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sessions`
--

CREATE TABLE IF NOT EXISTS `sessions` (
  `mail` varchar(64) NOT NULL,
  `sessionkey` varchar(64) NOT NULL,
  `lastused` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `mail` (`mail`),
  KEY `sessionkey` (`sessionkey`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- RELATIONEN DER TABELLE `sessions`:
--   `mail`
--       `users` -> `mail`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `mail` varchar(64) NOT NULL,
  `password` varchar(64) NOT NULL,
  `role` int(11) NOT NULL DEFAULT '1',
  `name` varchar(64) NOT NULL,
  `forename` varchar(64) DEFAULT NULL,
  `profilepicture` longblob,
  `phone` varchar(64) DEFAULT NULL,
  `street` varchar(64) DEFAULT NULL,
  `place` varchar(64) DEFAULT NULL,
  `companyinfo` text,
  `companypictures` longblob,
  PRIMARY KEY (`mail`(32)),
  UNIQUE KEY `mail` (`mail`),
  KEY `mail_2` (`mail`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- RELATIONEN DER TABELLE `users`:
--

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`offerid`) REFERENCES `offers` (`offerid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `activities_ibfk_2` FOREIGN KEY (`eventid`) REFERENCES `events` (`eventid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `activities_ibfk_3` FOREIGN KEY (`provider`) REFERENCES `users` (`mail`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`provider`) REFERENCES `users` (`mail`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `offers`
--
ALTER TABLE `offers`
  ADD CONSTRAINT `offers_ibfk_1` FOREIGN KEY (`provider`) REFERENCES `users` (`mail`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `participations`
--
ALTER TABLE `participations`
  ADD CONSTRAINT `participations_ibfk_1` FOREIGN KEY (`participant`) REFERENCES `users` (`mail`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `participations_ibfk_2` FOREIGN KEY (`activityid`) REFERENCES `activities` (`activityid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`mail`) REFERENCES `users` (`mail`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
