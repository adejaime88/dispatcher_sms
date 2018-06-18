-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 12-Abr-2018 às 22:38
-- Versão do servidor: 10.1.29-MariaDB
-- PHP Version: 7.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dispatcher`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `logsms`
--

CREATE TABLE `logsms` (
  `id` int(11) NOT NULL,
  `originalId` int(11) NOT NULL,
  `cliente` varchar(40) NOT NULL,
  `tipoSMS` varchar(40) NOT NULL,
  `dataVenc` date NOT NULL,
  `valor` decimal(10,0) NOT NULL,
  `cpfcnpj` varchar(20) NOT NULL,
  `nota` varchar(10) NOT NULL,
  `cheque` varchar(10) NOT NULL,
  `codbarras` varchar(50) NOT NULL,
  `dataSMS` datetime NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `smsTransmitido` tinyint(1) NOT NULL,
  `smsObs` varchar(255) NOT NULL,
  `chaveRegistro` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `logsms`
--
ALTER TABLE `logsms`
  ADD PRIMARY KEY (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
