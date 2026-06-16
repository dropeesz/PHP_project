-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 17/06/2026 às 01:11
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `caixinha`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `cotistas`
--

CREATE TABLE `cotistas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `saldo_investido` decimal(10,2) DEFAULT 0.00,
  `divida` decimal(10,2) DEFAULT 0.00,
  `juros_pendentes` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico`
--

CREATE TABLE `historico` (
  `id` int(11) NOT NULL,
  `cotista_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data` date DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimentacoes`
--

CREATE TABLE `movimentacoes` (
  `id` int(11) NOT NULL,
  `cotista_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `data` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `cotistas`
--
ALTER TABLE `cotistas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `historico`
--
ALTER TABLE `historico`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `movimentacoes`
--
ALTER TABLE `movimentacoes`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `cotistas`
--
ALTER TABLE `cotistas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `historico`
--
ALTER TABLE `historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `movimentacoes`
--
ALTER TABLE `movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=284;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
