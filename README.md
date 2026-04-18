# Monthly Budget Planner for Small Organizations and Firms

💙 A modern PHP + MySQL budget planning app for managing monthly budgets, weekly budgets, income, expenses, and reports.

## ✨ Highlights

- 🔐 User registration and login
- 💸 Add income and expense entries
- 🏷️ Create categories and budgets on the go
- 📅 Monthly dashboard tracking
- 🗓️ Independent weekly budget planning
- ⚠️ Budget limit warning system
- 🧾 Receipt-style monthly report
- 📊 Month-wise report filtering

## 🎯 What It Does

Monthly Budget Planner helps small organizations and firms:

- track monthly income
- record daily expenses
- organize spending under monthly budgets
- monitor weekly budget limits
- monitor budget usage
- review monthly summaries in a clean report format

## 🛠️ Tech Stack

- PHP
- MySQL
- HTML
- CSS
- XAMPP

## 🧩 Core Features

### 👤 Authentication

- User registration
- User login
- Session-based access control

### 💰 Expense Management

- Add income entries
- Add expense entries
- Save entries with date
- Link expense to category and budget

### 🏷️ Category & Budget Support

- Select existing category
- Add new category while saving entry
- Select existing budget
- Add new budget while saving entry
- Set monthly limit for new budget

### 📈 Budget Tracking

- Monthly budget usage
- Remaining amount display
- Warning when budget is exceeded
- Default `No Budget` support

### 🗓️ Monthly Logic

- Dashboard shows current month only
- Old records remain stored in database
- Monthly values reset automatically by calculation
- Report page can show any selected month

### 🧾 Monthly Report

- Receipt-style report layout
- Month selector
- Total income
- Total expense
- Balance left
- Budget summary
- Category summary

## 🗃️ Database Design

The app uses 4 main tables:

- `users`
- `categories`
- `budgets`
- `transactions`

### Table Overview

`users`
- stores account details

`categories`
- stores user-created spending categories

`budgets`
- stores monthly budget names and limits

`transactions`
- stores income and expense entries with date, category, and budget

## 🔄 Monthly Tracking Flow

1. User adds transactions with a date.
2. Data is saved permanently in the database.
3. Dashboard fetches only current month records.
4. Monthly Report fetches the selected month records.
5. Budget tracking changes month by month without deleting old data.

## 📂 Project Structure

```text
expense-planner/
├── index.php
├── register.php
├── dashboard.php
├── report.php
├── logout.php
├── auth.php
├── config.php
├── styles.css
└── expense_planner.sql
```

## 🌟 Key Ideas Behind The App

- Clean blue dashboard theme
- Simple and readable PHP structure
- Budget-first expense tracking
- Independent weekly budget tracking
- Monthly reporting without deleting history
- Easy flow for adding categories and budgets

## 🚀 Future Improvements

- Edit and delete transactions
- Export report as PDF
- Add charts and graphs
- Add yearly summary
- Add profile settings

## 📌 Summary

Monthly Budget Planner is a clean and practical web app for small organizations and firms to track money month by month, plan weekly spending, organize expenses under budgets, and generate simple reports in a modern interface.
