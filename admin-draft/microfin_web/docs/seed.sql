-- MicroFin Seed Data
-- Contains default INSERT statements for permissions, document types, roles, and users

SET FOREIGN_KEY_CHECKS=0;

-- Permissions
INSERT INTO `permissions` (`permission_id`, `permission_code`, `module`, `description`) VALUES 
(1,'VIEW_USERS','Users','Can view the list of users and employees'),
(2,'CREATE_USERS','Users','Can create new users and invite employees'),
(3,'MANAGE_ROLES','Roles','Can create custom roles and assign permissions'),
(4,'VIEW_CLIENTS','Clients','Can view client profiles and history'),
(5,'CREATE_CLIENTS','Clients','Can register new clients'),
(6,'VIEW_LOANS','Loans','Can view loan applications and active loans'),
(7,'CREATE_LOANS','Loans','Can draft new loan applications'),
(8,'APPROVE_LOANS','Loans','Can approve or reject Pending loans'),
(9,'PROCESS_PAYMENTS','Payments','Can receive and post loan payments'),
(10,'VIEW_REPORTS','Reports','Can generate and view financial reports'),
(11,'VIEW_APPLICATIONS','Applications','Can view Pending loan applications'),
(12,'MANAGE_APPLICATIONS','Applications','Can approve, reject, or process Pending loan applications'),
(13,'VIEW_CREDIT_ACCOUNTS','Credit Accounts','Can view borrower credit accounts, limit recommendations, and upgrade eligibility'),
(14,'EDIT_BILLING','System','Can edit billing and subscription settings');

-- Document Types (Full 41 items)
INSERT INTO `document_types` VALUES (1,'Valid ID Front','Front side of government-issued ID',1,1,NULL,NOW()),(2,'Valid ID Back','Back side of government-issued ID',1,1,NULL,NOW()),(3,'Proof of Income','Latest payslip, ITR, or bank statement',1,1,NULL,NOW()),(4,'Proof of Billing','Upload proof of address (utility bill, barangay certificate, etc.)',1,1,NULL,NOW()),(5,'Proof of Legitimacy Document','Any valid proof of legitimacy such as business permit, DTI, or SEC registration',1,1,'Business',NOW()),(6,'Business Financial Statements','Latest financial statements or income records',1,1,'Business',NOW()),(7,'Business Plan','Business plan or proposal (for new businesses)',0,1,'Business',NOW()),(8,'School Enrollment Certificate','Certificate of enrollment or admission letter',1,1,'Education',NOW()),(9,'School ID','Valid school ID',1,1,'Education',NOW()),(10,'Tuition Fee Assessment','Official assessment of tuition and fees',1,1,'Education',NOW()),(11,'Land Title/Lease Agreement','Proof of land ownership or lease',1,1,'Agricultural',NOW()),(12,'Farm Plan','Detailed farm plan or proposal',1,1,'Agricultural',NOW()),(13,'Medical Certificate','Medical certificate or hospital bill',1,1,'Medical',NOW()),(14,'Prescription/Treatment Plan','Doctor\'s prescription or treatment plan',0,1,'Medical',NOW()),(15,'Property Documents','Land title, tax declaration, or contract to sell',1,1,'Housing',NOW()),(16,'Construction Estimate','Detailed construction estimate or quotation',1,1,'Housing',NOW()),(17,'DTI/SEC Registration','Business registration documents',0,1,'Business',NOW()),(18,'Barangay Clearance','Clearance from local barangay',0,1,NULL,NOW()),(19,'Marriage Certificate','For married applicants',0,1,NULL,NOW()),(20,'Birth Certificate','Birth certificate copy',0,1,NULL,NOW()),(21,'National ID (PhilID/ePhilID)','Philippine Identification System ID including PhilID or ePhilID',0,1,NULL,NOW()),(22,'Passport','Philippine or foreign passport accepted as a valid identity document',0,1,NULL,NOW()),(23,'Driver\'s License','Driver\'s license or electronic driver\'s license accepted as a valid identity document',0,1,NULL,NOW()),(24,'UMID','Unified Multi-Purpose ID accepted as a valid identity document',0,1,NULL,NOW()),(25,'SSS ID','Social Security System ID or digitized SSS ID accepted as a valid identity document',0,1,NULL,NOW()),(26,'GSIS e-Card','Government Service Insurance System e-Card accepted as a valid identity document',0,1,NULL,NOW()),(27,'PRC ID','Professional Regulation Commission ID accepted as a valid identity document',0,1,NULL,NOW()),(28,'Postal ID','Postal ID accepted as a valid identity document',0,1,NULL,NOW()),(29,'Seaman\'s Book / SIRB','Seaman\'s Book or Seafarer\'s Identification and Record Book',0,1,NULL,NOW()),(30,'Senior Citizen ID','Senior Citizen ID accepted by some institutions as a valid identity document',0,1,NULL,NOW()),(31,'PWD ID','Person with Disability ID or NCDA-issued ID accepted by some institutions',0,1,NULL,NOW()),(32,'Voter\'s ID','Voter\'s ID or similar voter registration ID accepted by some institutions',0,1,NULL,NOW()),(33,'NBI Clearance','National Bureau of Investigation clearance accepted by some institutions as identity proof',0,1,NULL,NOW()),(34,'Police Clearance','Police clearance with photo and signature accepted by some institutions',0,1,NULL,NOW()),(35,'TIN ID','Tax Identification Number ID accepted by some institutions',0,1,NULL,NOW()),(36,'Company ID','Company ID accepted by some institutions but may not be valid for all loan or housing transactions',0,1,NULL,NOW()),(37,'Barangay ID','Barangay ID accepted by some institutions but may not be valid for all loan or housing transactions',0,1,NULL,NOW()),(38,'OFW ID','Overseas Filipino Worker ID accepted in some contexts',0,1,NULL,NOW()),(39,'OWWA ID','Overseas Workers Welfare Administration ID accepted in some contexts',0,1,NULL,NOW()),(40,'IBP ID','Integrated Bar of the Philippines ID accepted by some institutions',0,1,NULL,NOW()),(41,'Government Office / GOCC ID','Government office or GOCC-issued ID such as AFP or similar government entity ID',0,1,NULL,NOW());

-- User Roles
INSERT INTO `user_roles` (`role_id`, `tenant_id`, `role_name`, `role_description`, `is_system_role`) VALUES 
(1, NULL, 'Super Admin', 'Master Platform Administrator', 1);

-- Users
INSERT INTO `users` (`user_id`, `tenant_id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `role_id`, `user_type`, `status`) VALUES 
(1, NULL, 'superadmin', 'gleomir28@gmail.com', '$2y$10$RkgVfbgvX7CHzKVFlHSfQ.Kl3Dm1jMoRfdRimek7hYhTMLXVmy53C', 'Gleo', 'Mir', 1, 'Super Admin', 'Active');

-- Permissions Link
INSERT INTO `role_permissions` (role_id, permission_id) 
SELECT 1, permission_id FROM `permissions`;

SET FOREIGN_KEY_CHECKS=1;
