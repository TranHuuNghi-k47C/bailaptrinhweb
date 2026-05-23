-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost
-- Thời gian đã tạo: Th5 21, 2026 lúc 05:34 AM
-- Phiên bản máy phục vụ: 10.4.28-MariaDB
-- Phiên bản PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `quanlythuvien`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Books`
--

CREATE TABLE `Books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `image_url` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Books`
--

INSERT INTO `Books` (`id`, `title`, `author`, `category`, `quantity`, `image_url`, `created_at`) VALUES
(1, 'Lập trình PHP', 'Nguyễn Văn A', 'CNTT', 10, 'https://picsum.photos/200/300', '2026-05-19 08:37:04'),
(2, 'Lập trình PHP', 'Nguyễn Văn A', 'CNTT', 10, 'https://picsum.photos/200/300', '2026-05-19 08:38:09'),
(3, 'Java Cơ bản', 'Trần Văn B', 'CNTT', 5, 'https://picsum.photos/200/301', '2026-05-19 08:38:09'),
(4, 'Sách Nhật ký Đặng Thị Thùy Trâm', 'Đặng Thùy Trâm', 'Triết học', 3, 'img/books/nhật kí.jpg', '2026-05-19 08:42:56'),
(5, 'Buồn nôn', 'Jean Paul Sartre', 'Triết học', 2, 'img/books/tiểu thuyết.jpg', '2026-05-19 08:42:56'),
(6, 'Các triết thuyết lớn', 'Dominique Folscheid', 'Triết học', 6, 'img/books/triết lớn.jpg', '2026-05-19 08:42:56'),
(7, 'Bàn về đạo nho', 'Nguyễn Khắc Viện', 'Triết học', 7, 'img/books/đạo nho.jpg', '2026-05-19 08:42:56'),
(8, 'Bên kia thiện ác', 'Friedrich Nietzsche', 'Triết học', 98, 'img/books/thiện ác.jpg', '2026-05-19 08:42:56'),
(9, 'Consilience the unity of knowledge', 'Edward O. Wilson', 'Triết học', 5, 'img/books/uniti.jpg', '2026-05-19 08:42:56'),
(10, 'Chủ nghĩa dân tộc sinh tồn', 'Nguyễn Ngọc Huy', 'Triết học', 5, 'img/books/sinh tồn.jpg', '2026-05-19 08:42:56');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `BorrowRequests`
--

CREATE TABLE `BorrowRequests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `borrow_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `total_fee` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Notifications`
--

CREATE TABLE `Notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Reviews`
--

CREATE TABLE `Reviews` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Reviews`
--

INSERT INTO `Reviews` (`id`, `book_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(13, 84, 1, 2, 'uouououou', '2026-05-21 03:32:56');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Users`
--

CREATE TABLE `Users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','admin','librarian') NOT NULL DEFAULT 'student',
  `major_name` varchar(100) DEFAULT NULL,
  `cohort` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Users` (simplified version - 3 sample users)
--

INSERT INTO `Users` (`id`, `username`, `email`, `phone_number`, `password`, `role`, `major_name`, `cohort`, `full_name`) VALUES
(1, '441010001', '441010001@student.qnu.edu.vn', '0942227922', '$2y$10$IbGvO8glHWe0KfAAI9DgUuEXsAp3GEX7S9rFKT2bzQOp0XJMG1I0W', 'student', 'Công nghệ thông tin', 44, 'Sinh viên 441010001'),
(1800, 'admin', 'admin@example.com', '0900000000', '$2y$10$4Y2Bsma3OVc5.5fQ.UWXsutqDRVhKHPv7LfjNc6eAmPSW5SvNBqCa', 'admin', 'Ban Quản Trị', 0, 'Quản trị viên hệ thống'),
(1802, 'thuthu01', 'thuthu01@example.com', '0900000001', '$2y$10$xfg0VaOxxePM1Wy//7QPNOKO9JC4zCtlHtJfa/earRMsmrrhVyyP6', 'librarian', 'Ban Thư Viện', 0, 'Thủ thư 01');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `Books`
--
ALTER TABLE `Books`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `BorrowRequests`
--
ALTER TABLE `BorrowRequests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Chỉ mục cho bảng `Notifications`
--
ALTER TABLE `Notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_ibfk_1` (`user_id`);

--
-- Chỉ mục cho bảng `Reviews`
--
ALTER TABLE `Reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_book` (`user_id`,`book_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Chỉ mục cho bảng `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `Books`
--
ALTER TABLE `Books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT cho bảng `BorrowRequests`
--
ALTER TABLE `BorrowRequests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `Notifications`
--
ALTER TABLE `Notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `Reviews`
--
ALTER TABLE `Reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `Users`
--
ALTER TABLE `Users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1804;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `BorrowRequests`
--
ALTER TABLE `BorrowRequests`
  ADD CONSTRAINT `fk_borrow_user` FOREIGN KEY (`user_id`) REFERENCES `Users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_borrow_book` FOREIGN KEY (`book_id`) REFERENCES `Books` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `Notifications`
--
ALTER TABLE `Notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `Reviews`
--
ALTER TABLE `Reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `Books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `Users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
