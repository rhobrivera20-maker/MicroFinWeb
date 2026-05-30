# MicroFin Platform - Complete Feature List & Inclusions

## Overview
MicroFin is a comprehensive cloud-based core banking platform designed for Microfinance Institutions (MFIs), SACCOs, and Cooperatives. This document details all included features, modules, and capabilities.

---

## 🏢 Multi-Tenant Cloud Architecture

### Platform Shell (Super Admin)
- **Tenant Management**: Create, manage, suspend, and delete tenant accounts
- **Application Workflow**: Review and approve/reject new tenant applications
- **Subscription Billing**: Track MRR, billing cycles, and payment history
- **Plan Management**: Starter (2,000 clients, 1,000 staff) and Enterprise (unlimited)
- **Audit Trail**: Complete system activity logging across all tenants
- **Backup & Restore**: Database backup management with restore capabilities
- **Reports**: Revenue analytics, tenant activity, inquiry tracking, billing summaries
- **Sales Dashboard**: Revenue overview, top performing tenants, transaction history
- **Super Admin Accounts**: Multi-admin access with role management
- **Receipt Management**: Generate and manage tenant billing receipts

### Tenant Isolation
- Each tenant has isolated database schema
- Complete data separation (no commingling)
- Individual branding and customization
- Separate login portals per tenant

---

## 📱 Mobile Applications

### Borrower Mobile App (Flutter/Dart)
**Screens & Features:**
- **Login Screen**: Tenant-aware login (username@company format)
- **Dashboard**: Credit limit display, active loans, recent activity, notifications
- **Loan Application**: Multi-step application with document upload
- **Loan Details**: Full loan information, payment schedule, early settlement calculator
- **Payments**: View all loans, make payments via GCash/PayMaya/Bank Transfer
- **Transaction History**: Complete payment and transaction records
- **Profile Management**: Personal info, settings, password change, 2FA
- **Identity Verification**: 4-step verification process with ID upload
- **Credit Score**: Display credit score, rating, and history
- **Support Center**: FAQ and customer support access

**Mobile API Endpoints (37+):**
- Authentication (login, register, password reset, OTP verification)
- Loan applications (apply, view applications, get details)
- Loan management (view loans, pay loans, calculate termination fees)
- Payments (payment processing, PayMongo integration, transaction history)
- Profile management (get/update profile, change password, email verification)
- Document upload (ID verification, document types)
- Notifications (clear, get notifications)
- Tenant resolution (find accounts, resolve tenant reference)

### Staff Portal (Web)
**Dashboard:**
- Welcome banner with time-based greeting
- Stat cards (pending applications, active loans, overdue loans, collections, active clients)
- Recent applications list
- Quick actions (receipts, loans, reports)

**Modules:**
- **Client Management**: View, search, and manage borrowers
- **Credit Accounts**: Review credit limits, scores, upgrade eligibility, risk levels
- **Loan Applications**: Review, inspect documents, approve/reject applications
- **Loans Management**: Disburse loans, monitor active loans, track payments
- **Receipts & Transactions**: View payment history and transactions
- **Team Directory**: Manage staff accounts and permissions
- **Reports & Analytics**: Financial performance and portfolio overview
- **My Profile**: Personal information and security settings

---

## 💻 Admin Panel (Tenant Admin)

### Dashboard
- Workspace overview with company name and health status
- Stat cards (active clients, collections, active staff, available capital)
- Capacity snapshot with utilization progress bars
- Quick action cards for common tasks
- Recent audit logs

### Staff Accounts
- Create admin accounts and add staff members
- Role assignments with granular permissions
- Staff list with status management
- Add staff modal with profile configuration
- Roles & Permissions management

### Loan Products
- Product configuration (name, type, description)
- Interest settings (rate, type, grace period)
- Amount & term limits
- Fees & charges (processing, insurance, service charge, documentary stamp)
- Termination fee configuration (multiple fee types)
- Product list with edit/delete actions

### Credit Control Policy
- **Overview**: Credit policy badges, scoring model presets, approval guardrails
- **Scoring Weights**: Income, employment, credit history, collateral, character, business
- **Credit & Limits**: Approval workflow, upgrade rules, starting limits
- **Required Documents**: Configure mandatory and optional documents

### Funds Management
- **Overview**: Capital KPIs dashboard with summary cards
- **Capital**: Initial setup, capital dashboard, activity log
- **Transaction History**: View all capital adjustments, disbursements, replenishments
- **Payment Methods**: Disbursement methods management

### Website Editor
- Live iframe preview of public-facing website
- Website builder for content management
- Page configuration and design customization
- Template selection (3+ templates available)

### Branding
- Brand Identity (company name, logo upload)
- Theme & Fonts (9 font options)
- Color Configuration (brand, background, card, text, border)
- Live preview of changes

### Billing & Subscription
- Plan overview with current plan details
- Usage & limits tracking (clients, staff)
- Plan management (change plan, cancel subscription)
- Payment history

### Payment Info
- Payment methods management
- Saved cards table
- Add payment method modal

### Receipts
- Receipt history with export functionality
- Period filtering (month, year)
- Receipt cards with invoice details
- View and download PDF receipts

### Personal Profile
- Profile summary with avatar
- Basic information management
- Email change with OTP verification
- Password change
- 2FA settings

### Audit Trail
- Complete system activity logging
- Date/time, username, role, action type, description
- View details for each entry

---

## 🔧 Backend API (Admin Panel)

### API Endpoints (13+)
- **api_applications**: Loan application management (approve, reject, status updates)
- **api_auth**: Authentication and session management
- **api_clients**: Client data management
- **api_dashboard**: Dashboard statistics and metrics
- **api_loans**: Loan management (create, release, update status)
- **api_payments**: Payment processing and posting
- **api_profile_email_change**: Email change workflow
- **api_profile_password_change**: Password management
- **api_profile_update**: Profile data updates
- **api_team_invite**: Staff invitation system
- **api_team_manage**: Staff account management
- **api_theme_preference**: UI theme settings
- **api_walk_in**: Walk-in client registration

---

## 🎨 Website Builder

### Templates
- **Template 1**: Modern landing page design
- **Template 2**: Professional business layout
- **Template 3**: Fundline Modern Enterprise (with loan calculator)

### Customization Options
- Brand colors and fonts
- Logo upload
- Hero section customization
- Services/products section
- Loan calculator integration
- Contact information
- Footer customization
- Section background styles (solid/gradient)

---

## 🔒 Security Features

### Authentication
- Multi-factor authentication (2FA)
- Secure password hashing
- Session management
- OTP verification for sensitive actions

### Data Security
- End-to-end encryption (AES-256)
- TLS 1.3 for data in transit
- Tenant data isolation
- Automated backups
- Audit trail for all actions

### Access Control
- Role-based permissions
- Granular access control
- Staff role management
- Permission inheritance

---

## 📊 Reports & Analytics

### Admin Reports
- PAR (Portfolio at Risk) reports
- Balance sheets
- Income statements
- Tenant activity reports
- Billing summaries
- Revenue analytics

### Staff Reports
- Financial performance overview
- Portfolio overview
- Client statistics
- Loan performance
- Collection reports

### Super Admin Reports
- Revenue by tenant
- Transaction history
- Plan distribution
- User growth charts
- Tenant activity breakdown

---

## 💳 Payment Integration

### Payment Gateways
- **PayMongo Integration**: GCash, PayMaya, credit/debit cards
- **Bank Transfer**: Manual bank transfer processing
- **Cash**: Over-the-counter payment recording

### Payment Features
- Automatic payment posting
- Receipt generation
- Email notifications
- Payment verification
- Transaction history
- Early settlement calculation

---

## 📧 Notifications

### Email Notifications
- Payment reminders
- Due date alerts
- Application status updates
- Account notifications
- Staff invitations
- Password reset

### In-App Notifications
- Real-time notification center
- Notification badges
- Notification history
- Clear notifications

---

## 🛠️ Technical Features

### Database
- MySQL/MySQLi with PDO
- Multi-tenant schema isolation
- Automated migrations
- Backup & restore utilities

### API Architecture
- RESTful API design
- JSON responses
- CORS support
- Error handling
- Request validation

### Frontend Technologies
- **Admin Panel**: PHP, JavaScript, CSS, Material Symbols
- **Staff Portal**: PHP, JavaScript, CSS
- **Mobile App**: Flutter/Dart
- **Website Builder**: PHP, JavaScript, CSS

### Deployment
- Cloud-ready architecture
- Environment configuration
- Database connection management
- File upload handling
- Asset versioning

---

## 📦 Included Components

### Admin Panel Files (37+)
- Main admin interface (admin.php)
- Setup wizard
- Website editor with templates
- Staff portal components
- Receipt generation
- Credit policy console
- Partial templates and components

### Backend API Files (50+)
- Admin API endpoints (13)
- Mobile API endpoints (37)
- Utility functions
- Database connection
- Configuration management

### Mobile App Files (28+)
- Main application screens
- Utility classes
- API configuration
- Models and widgets
- Theme management

### Documentation
- Database schema (fresh_start.sql)
- Seed data (seed.sql)
- API documentation
- Implementation guides

---

## 🎯 Plan Comparison

### Starter Plan (₱4,999/month)
- **2,000 Max Clients**
- **1,000 Max Staff**
- Enterprise Core Engine
- Branded Mobile APK
- 3 Website Templates
- Loan Products & Credit Policy
- Funds Management & Capital Tracking
- Staff Portal & Role Management
- Reports & Analytics Dashboard
- Client Mobile App (Borrower)
- Payment Processing Integration
- Automated Notifications
- Audit Trail & Security

### Enterprise Plan (₱14,999/month)
- **Unlimited Clients**
- **Unlimited Staff**
- White-Labeled Mobile App
- All Premium Templates
- Isolated Cloud Architecture
- All Starter features PLUS:
- Priority Technical Support
- Custom Integrations

---

## 🚀 Getting Started

### Setup Process
1. **Super Admin**: Provision tenant account
2. **Tenant Setup**: Complete setup wizard
3. **Staff Creation**: Add staff members with roles
4. **Product Configuration**: Set up loan products
5. **Credit Policy**: Configure approval rules
6. **Capital Initialization**: Set up lending capital
7. **Website Customization**: Brand public-facing site
8. **Mobile App**: Distribute branded APK to clients

### Onboarding Support
- Setup wizard guidance
- Tutorial system
- Documentation
- Technical support (Enterprise: Priority)

---

## 📞 Support

### Starter Plan
- Email support
- Documentation access
- Community resources

### Enterprise Plan
- Priority technical support
- Custom integrations
- Dedicated assistance
- Advanced troubleshooting

---

## 🔄 Updates & Maintenance

### Platform Updates
- Regular security patches
- Feature enhancements
- Bug fixes
- Performance improvements

### Data Management
- Automated backups
- Restore capabilities
- Data export options
- Migration support

---

*This document represents the complete feature set as of the current version. Features may be enhanced or modified in future updates.*
