# trip.com — Flight Booking System

A full-stack flight booking web application built with PHP and MySQL, designed as an academic project for BSIT 3A at the University of Cebu. Inspired by the look and feel of Trip.com, the system supports guest and member bookings, an admin panel, loyalty coins, and promo codes.

**Live Demo:** https://trip-project.free.nf

---

## Features

### Guest Users
- Search one-way and round-trip flights by route, date, passengers, and seat class
- Book flights without an account (email-only guest booking)
- Find existing bookings by email address
- View full booking confirmation and itinerary

### Registered Members
- All guest features, plus:
- Trip Coins loyalty system — earn coins per booking, tiered membership (Silver → Black Diamond)
- Booking history with filter tabs (Upcoming / Completed / Cancelled)
- Cancel confirmed bookings with automatic seat restoration
- Profile management (name, phone, nationality, date of birth)
- Password change

### Admin Panel
- Manage flights — create, edit, toggle availability
- Manage bookings — view all bookings, cancel with refund
- Round-trip seat restoration on cancellation

### System-wide
- Promo code support (percentage and fixed-amount discounts)
- Multiple payment methods — GCash, Maya, credit/debit card
- CVV and password hold-to-reveal inputs
- Custom 404 page
- Responsive design — works on mobile and desktop

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 (procedural, no framework) |
| Database | MySQL (MySQLi with prepared statements) |
| Frontend | Bootstrap 5.3, Bootstrap Icons 1.11 |
| Fonts | Plus Jakarta Sans, Newsreader (Google Fonts) |
| Hosting | InfinityFree (shared hosting, Linux MySQL) |

---

## Project Structure

```
dbWeb/
├── admin/
│   ├── dashboard.php          # Admin overview
│   ├── manage_flights.php     # Flight CRUD
│   └── manage_bookings.php    # Booking management + cancellation
├── assets/
│   ├── nash.jpg               # Developer photo
│   └── favicon.jpg            # Site favicon
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── config/
│   ├── db.php                 # Database connection
│   └── airports.php           # IATA code → city name map
├── flights/
│   ├── search.php             # Flight search results + sort
│   ├── book.php               # Booking form (guest + member)
│   ├── confirmation.php       # Booking confirmation / itinerary
│   └── find-booking.php       # Guest email booking lookup
├── layout/
│   ├── layout.php             # Global head, navbar, JS utilities
│   └── footer.php             # Footer, developer card, promo codes
├── user/
│   ├── dashboard.php          # My Bookings (with filter tabs)
│   └── profile.php            # Profile + password change
├── 404.php                    # Custom not-found page
└── index.php                  # Homepage + flight search form
```

---

## Database Schema

The system uses 8 tables:

| Table | Description |
|---|---|
| `user` | Registered users and guest accounts |
| `flight` | Flight schedules, routes, fares, seat availability |
| `airliner` | Airline names and IATA codes |
| `booking` | Booking records with status and totals |
| `bookingdetails` | Per-passenger, per-flight-leg booking rows |
| `payment` | Payment method and transaction reference |
| `promotion` | Promo codes (percentage or fixed discount) |
| `review` | User flight reviews |

> **Note:** The database runs on Linux MySQL with `lower_case_table_names=0` (case-sensitive). All table names in SQL queries must be **lowercase**.

---

## Local Setup

### Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB
- Apache or Nginx (XAMPP / Laragon recommended on Windows)

### Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/your-repo-name.git
   ```

2. **Import the database**
   - Open phpMyAdmin or MySQL CLI
   - Create a database named `flightbooking`
   - Import `flightbooking.sql` from the project root

3. **Configure the database connection**

   Edit `config/db.php`:
   ```php
   $conn = new mysqli("localhost", "root", "", "flightbooking");
   ```

4. **Start your local server**
   - Place the project folder inside `htdocs/` (XAMPP) or `www/` (Laragon)
   - Visit `http://localhost/dbWeb`

---

## Demo Credentials

| Role | Email | Password |
|---|---|---|
| Admin | admin@trip.com | password |
| Member | user@trip.com | password |
| Guest | *(no login needed — enter any email when booking)* | — |

### Demo Promo Codes

| Code | Discount |
|---|---|
| `TRIP10` | 10% off |
| `SUMMER500` | ₱500 off |
| `FLYPH20` | 20% off |

---

## Membership Tiers (Trip Coins)

Coins are earned per booking and determine member tier:

| Tier | Minimum Coins |
|---|---|
| Silver | 0 |
| Gold | 500 |
| Platinum | 2,000 |
| Diamond | 5,000 |
| Diamond+ | 10,000 |
| Black Diamond | 20,000 |

---

## Developer

**Nash T. Riobuya**
BSIT 3A · 3rd Year · University of Cebu

Passionate about game development, web development, and Android application creation.

> *"Computer science education cannot make anybody an expert programmer any more than studying brushes and pigment can make somebody an expert painter."*
> — Eric S. Raymond

---

## Disclaimer

This project is for **academic demonstration purposes only**. It is not a real flight booking platform. No actual tickets are issued, and no real payments are processed.
