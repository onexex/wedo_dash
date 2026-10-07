-- Structure-only snapshot of the app schema for the regression tests (NO data).
-- Regenerate: mysqldump --no-data --skip-comments wedodb2020 | sed -E 's/ AUTO_INCREMENT=[0-9]+//' > tests/fixtures/schema.sql
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accessrights` (
  `ARID` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) NOT NULL,
  `EF` int(100) DEFAULT 1,
  `e201` int(11) NOT NULL DEFAULT 2,
  `alas` int(11) NOT NULL DEFAULT 2,
  `otob` int(11) NOT NULL DEFAULT 1,
  `ob` int(11) NOT NULL DEFAULT 2,
  `sob` int(11) NOT NULL DEFAULT 1,
  `ot` int(11) NOT NULL DEFAULT 2,
  `eo` int(11) NOT NULL DEFAULT 2,
  `alasv` int(11) NOT NULL DEFAULT 1,
  `lilov` int(11) NOT NULL DEFAULT 1,
  `darv` int(11) NOT NULL DEFAULT 1,
  `eov` int(11) NOT NULL DEFAULT 1,
  `alphav` int(11) NOT NULL DEFAULT 1,
  `obv` int(11) NOT NULL DEFAULT 1,
  `atv` int(11) NOT NULL DEFAULT 1,
  `arights` int(11) NOT NULL DEFAULT 1,
  `eemployee` int(11) NOT NULL DEFAULT 1,
  `e201d` int(11) NOT NULL DEFAULT 1,
  `agncy` int(11) NOT NULL DEFAULT 1,
  `comp` int(11) NOT NULL DEFAULT 1,
  `dep` int(11) NOT NULL DEFAULT 1,
  `pos` int(11) NOT NULL DEFAULT 1,
  `jl` int(11) NOT NULL DEFAULT 1,
  `hmo` int(11) NOT NULL DEFAULT 1,
  `est` int(11) NOT NULL DEFAULT 1,
  `rel` int(11) NOT NULL DEFAULT 1,
  `classf` int(11) NOT NULL DEFAULT 1,
  `wt` int(11) NOT NULL DEFAULT 1,
  `wd` int(11) NOT NULL DEFAULT 1,
  `ur` int(11) NOT NULL DEFAULT 1,
  `srch` int(11) NOT NULL DEFAULT 1,
  `updte` int(11) NOT NULL DEFAULT 1,
  `idcard` int(11) NOT NULL DEFAULT 1,
  `gcorner` int(11) NOT NULL DEFAULT 2,
  `cddv` int(11) NOT NULL DEFAULT 1,
  `emv` int(11) NOT NULL DEFAULT 1,
  `piv` int(11) NOT NULL DEFAULT 1,
  `lval` int(11) DEFAULT 1,
  `tlv` int(11) NOT NULL DEFAULT 1,
  `otfs` int(11) NOT NULL DEFAULT 1,
  `hldy` int(1) NOT NULL DEFAULT 1,
  `gprdv` int(11) NOT NULL DEFAULT 1,
  `obval` int(11) NOT NULL DEFAULT 1,
  `eoval` int(11) NOT NULL DEFAULT 1,
  `pfam` int(11) NOT NULL DEFAULT 1,
  `msgfile` int(11) NOT NULL DEFAULT 1,
  `msgdel` int(11) NOT NULL DEFAULT 1,
  `fdetls` int(11) NOT NULL DEFAULT 1,
  `nwblogs` int(11) NOT NULL DEFAULT 1,
  `notif` int(11) NOT NULL DEFAULT 2,
  `dashboard` int(11) NOT NULL DEFAULT 1,
  `logintheme` int(11) NOT NULL DEFAULT 1,
  `lcreaditview` int(11) NOT NULL DEFAULT 1,
  `lcreditedit` int(11) NOT NULL DEFAULT 1,
  `SPPContrib` int(11) NOT NULL DEFAULT 1,
  `ams` int(11) NOT NULL DEFAULT 1,
  `payroll` int(10) NOT NULL DEFAULT 1,
  `debitadvise` int(12) NOT NULL DEFAULT 1,
  `debitadvisesettings` int(12) NOT NULL DEFAULT 1,
  `payslipt` int(12) NOT NULL DEFAULT 1,
  `datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `payslipfunction` int(12) NOT NULL DEFAULT 0,
  `coe` int(12) NOT NULL DEFAULT 1,
  `coefunction` int(12) NOT NULL DEFAULT 1,
  `checkregister` int(12) NOT NULL DEFAULT 1,
  `memo` int(12) DEFAULT 1,
  `payeereg` int(12) NOT NULL DEFAULT 1,
  `bookletreg` int(12) NOT NULL DEFAULT 1,
  `schedv` int(11) NOT NULL DEFAULT 1,
  `access_13` int(2) DEFAULT 1,
  `access_13_attachement` int(2) DEFAULT 1,
  PRIMARY KEY (`ARID`),
  KEY `ix_accessrights_empid` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `adjusment2` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(250) DEFAULT NULL,
  `Amount` varchar(250) DEFAULT NULL,
  `pdate` date DEFAULT NULL,
  `Remarks` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agency` (
  `ASID` int(10) NOT NULL AUTO_INCREMENT,
  `AgencyID` varchar(12) NOT NULL,
  `AgencyName` varchar(20) NOT NULL,
  `IsActive` int(20) NOT NULL,
  PRIMARY KEY (`ASID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `amsarchive` (
  `id` int(250) NOT NULL AUTO_INCREMENT,
  `fname` varchar(250) DEFAULT NULL,
  `lname` varchar(250) DEFAULT NULL,
  `pos` varchar(250) DEFAULT NULL,
  `empdatesfrom` varchar(250) DEFAULT NULL,
  `empdatesto` varchar(250) DEFAULT NULL,
  `employmentstatus` varchar(250) DEFAULT NULL,
  `reasonforleaving` varchar(250) DEFAULT NULL,
  `derogatoryrecords` varchar(250) DEFAULT NULL,
  `clearance` varchar(250) DEFAULT NULL,
  `salary` varchar(250) DEFAULT NULL,
  `addremarks` varchar(250) DEFAULT NULL,
  `pedngingresignation` varchar(250) DEFAULT NULL,
  `verifiedby` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `datetime` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `aid` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) NOT NULL,
  `Title` varchar(100) NOT NULL,
  `ADesc` varchar(1000) NOT NULL,
  `ADate` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `annseen` (
  `seenid` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `EmpID` varchar(50) NOT NULL,
  `FSeenDate` datetime NOT NULL,
  `LSeenDate` datetime NOT NULL,
  `Status` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`seenid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendancelog` (
  `LogID` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(20) DEFAULT NULL,
  `wsched` varchar(50) DEFAULT NULL,
  `wsched2` varchar(20) DEFAULT NULL,
  `WSFrom` date DEFAULT NULL,
  `TimeIn` datetime DEFAULT NULL,
  `WSTo` date DEFAULT NULL,
  `TimeOut` datetime DEFAULT NULL,
  `MinsLack` double DEFAULT NULL,
  `MinsLack2` double DEFAULT NULL,
  `DateTimeInput` datetime DEFAULT current_timestamp(),
  `durationtime` varchar(50) DEFAULT '0',
  PRIMARY KEY (`LogID`),
  KEY `ix_attendancelog_emp_day` (`EmpID`,`WSFrom`),
  KEY `ix_attendancelog_day` (`WSFrom`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_category` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_desc` varchar(100) NOT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_edit` (
  `IsID` int(11) NOT NULL AUTO_INCREMENT,
  `IsDes` varchar(50) NOT NULL,
  PRIMARY KEY (`IsID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_post` (
  `Post_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Post_Category` int(11) NOT NULL,
  `Post_Title` varchar(100) NOT NULL,
  `Post_Publish_Date` datetime NOT NULL,
  `Post_Content` varchar(60000) NOT NULL,
  `Post_image` varchar(500) NOT NULL,
  `Author_ID` varchar(50) NOT NULL,
  `Post_URL` varchar(500) NOT NULL,
  `Post_Stat` int(11) NOT NULL,
  `Post_IsEdit` int(11) NOT NULL,
  PRIMARY KEY (`Post_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_status` (
  `SId` int(11) NOT NULL AUTO_INCREMENT,
  `Description` varchar(50) NOT NULL,
  PRIMARY KEY (`SId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_typeofuser` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(100) NOT NULL,
  `TypeDesc` varchar(100) NOT NULL,
  `typeID` int(10) NOT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookletbankinfo` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `bankname` varchar(250) NOT NULL,
  `dti` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `status` int(12) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookletinfo` (
  `id` int(250) NOT NULL AUTO_INCREMENT,
  `bankinfoid` int(250) NOT NULL,
  `bookletfrom` varchar(250) NOT NULL,
  `bookleto` varchar(250) NOT NULL,
  `dateinputed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` int(250) NOT NULL DEFAULT 0,
  `delstat` int(2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cal_values` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `category` int(20) NOT NULL,
  `avgNoDaysYr` int(10) NOT NULL,
  `HrsPerDay` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `centertime` (
  `CTID` int(11) NOT NULL AUTO_INCREMENT,
  `CTDate` date DEFAULT NULL,
  `CTTime` varchar(10) DEFAULT NULL,
  `CTRemarks` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  PRIMARY KEY (`CTID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checkregister` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payee` varchar(250) DEFAULT NULL,
  `bankinfo` varchar(250) DEFAULT NULL,
  `checkno` varchar(250) DEFAULT NULL,
  `checkdate` date DEFAULT NULL,
  `checkamount` varchar(15) DEFAULT NULL,
  `remarks` varchar(250) DEFAULT NULL,
  `dti` datetime(6) DEFAULT NULL,
  `status` int(12) DEFAULT 10,
  `bklid` int(12) NOT NULL,
  `bnkid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `CSID` int(11) NOT NULL AUTO_INCREMENT,
  `CompanyID` varchar(10) NOT NULL,
  `CompanyDesc` varchar(30) NOT NULL,
  `AgencyID` varchar(20) DEFAULT NULL,
  `logopath` varchar(100) NOT NULL,
  `comcolor` varchar(50) NOT NULL,
  `compcode` varchar(50) NOT NULL,
  PRIMARY KEY (`CSID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `condebitca` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `conID` varchar(12) NOT NULL,
  `CA_Number` varchar(250) NOT NULL,
  `CA_Status` int(12) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credit` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) NOT NULL,
  `CT` float NOT NULL,
  `CTH` float NOT NULL,
  PRIMARY KEY (`sid`),
  UNIQUE KEY `ux_credit_emp` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
CREATE TABLE `credit_years` (
  `leave_year` smallint(6) NOT NULL,
  `started_by` varchar(50) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `employees` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`leave_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `credit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) NOT NULL,
  `leave_year` smallint(6) NOT NULL,
  `action` varchar(10) NOT NULL,
  `old_ct` decimal(9,4) DEFAULT NULL,
  `old_cth` decimal(9,4) DEFAULT NULL,
  `new_ct` decimal(9,4) NOT NULL,
  `new_cth` decimal(9,4) NOT NULL,
  `changed_by` varchar(50) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_credit_log_emp` (`EmpID`,`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cutoffinfo` (
  `CTID` int(11) NOT NULL AUTO_INCREMENT,
  `Cstart` varchar(10) NOT NULL,
  `Cend` varchar(10) NOT NULL,
  `PYDate` varchar(10) NOT NULL,
  `Loan` int(10) NOT NULL,
  `SL` int(10) NOT NULL,
  `Tax` int(10) NOT NULL,
  `GovDues` int(10) NOT NULL,
  PRIMARY KEY (`CTID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dars` (
  `DARSID` int(10) NOT NULL AUTO_INCREMENT,
  `EMPID` varchar(20) NOT NULL,
  `EmpActivity` varchar(250) DEFAULT NULL,
  `DarDateTime` datetime DEFAULT NULL,
  PRIMARY KEY (`DARSID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `debitcontact` (
  `sid` int(250) NOT NULL AUTO_INCREMENT,
  `conSalutation` varchar(12) DEFAULT NULL,
  `conFName` varchar(250) DEFAULT NULL,
  `conMInitial` varchar(250) DEFAULT NULL,
  `conLName` varchar(250) DEFAULT NULL,
  `conPosition` varchar(250) DEFAULT NULL,
  `conBranch` varchar(250) DEFAULT NULL,
  `conCity` varchar(250) DEFAULT NULL,
  `conStatus` int(250) DEFAULT 1,
  `conOthers` varchar(250) DEFAULT NULL,
  `conCAId` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `debithistory` (
  `sid` int(12) NOT NULL AUTO_INCREMENT,
  `debitdate` varchar(15) DEFAULT NULL,
  `debitbranch` varchar(50) DEFAULT NULL,
  `debitcity` varchar(50) DEFAULT NULL,
  `payday` varchar(15) DEFAULT NULL,
  `dtpinput` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `DepartmentID` int(11) NOT NULL AUTO_INCREMENT,
  `DepartmentDesc` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `CompID` varchar(20) NOT NULL,
  PRIMARY KEY (`DepartmentID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `earlyout` (
  `SID` int(11) NOT NULL AUTO_INCREMENT,
  `EMPID` varchar(50) NOT NULL,
  `EmpISID` varchar(50) NOT NULL,
  `LogID` int(50) NOT NULL,
  `FDate` datetime NOT NULL DEFAULT current_timestamp(),
  `DFile` date NOT NULL,
  `EOfrom` varchar(10) DEFAULT NULL,
  `Purpose` varchar(150) NOT NULL,
  `IS_remark` varchar(150) DEFAULT NULL,
  `HR_remark` varchar(150) DEFAULT NULL,
  `IS_updated` datetime NOT NULL DEFAULT current_timestamp(),
  `HR_updated` datetime NOT NULL DEFAULT current_timestamp(),
  `DateTimeUpdated` datetime NOT NULL,
  `DateTimeInputed` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` int(11) NOT NULL,
  PRIMARY KEY (`SID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empdetails` (
  `Seq_ID` int(50) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) DEFAULT NULL,
  `EmpUN` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpPW` varchar(500) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpRoleID` int(11) DEFAULT NULL,
  `CatType` int(10) NOT NULL DEFAULT 1,
  `EmpISID` varchar(50) DEFAULT NULL,
  `EmpdepID` int(11) DEFAULT NULL,
  `EmpCompID` varchar(50) DEFAULT NULL,
  `EmpWSID` int(11) DEFAULT NULL,
  `EmpRDID` int(11) DEFAULT NULL,
  `EmpDateHired` date DEFAULT NULL,
  `EmpDOR` date DEFAULT NULL,
  `EmpDateResigned` date DEFAULT NULL,
  `EmpStatID` int(11) DEFAULT NULL,
  `EmpLogStat` int(11) DEFAULT 1,
  `EmpAD` date DEFAULT NULL,
  `AgencyID` varchar(200) DEFAULT NULL,
  `HMO_ID` varchar(200) DEFAULT NULL,
  `remember_hash` varchar(255) DEFAULT NULL,
  `remember_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`Seq_ID`),
  UNIQUE KEY `ix_empdetails_empid` (`EmpID`),
  KEY `Seq_ID` (`Seq_ID`),
  KEY `ix_empdetails_empisid` (`EmpISID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empdetails2` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(30) DEFAULT NULL,
  `EmpBasic` decimal(10,0) DEFAULT 1,
  `EmpAllowance` decimal(10,0) DEFAULT 1,
  `EmpHRate` decimal(10,0) DEFAULT 1,
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empe201files` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `EMPID` varchar(20) NOT NULL,
  `EmpfileN` varchar(50) NOT NULL,
  `EmpProFPath` varchar(50) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empeducationalbackground` (
  `EB_ID` int(15) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) DEFAULT NULL,
  `Name_of_School` varchar(100) DEFAULT NULL,
  `Program` varchar(100) DEFAULT NULL,
  `Year_Started` varchar(50) DEFAULT NULL,
  `Year_End` varchar(50) DEFAULT NULL,
  `School_Address` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`EB_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empjobdesc` (
  `EJID` int(11) NOT NULL AUTO_INCREMENT,
  `JID` int(11) NOT NULL,
  `EmpID` varchar(50) NOT NULL,
  PRIMARY KEY (`EJID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `ESID` int(10) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) NOT NULL,
  `EmpSuffix` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpLN` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpFN` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpMN` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `PosID` int(11) DEFAULT NULL,
  `WithSub` smallint(6) DEFAULT 1,
  `EmpStatusID` int(11) DEFAULT 1,
  `EmployeeIDNumber` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ESID`),
  UNIQUE KEY `ix_employees_empid` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empprofiles` (
  `ProfSID` int(10) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(100) DEFAULT NULL,
  `EmpAddress1` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpAddDis` varchar(200) DEFAULT NULL,
  `EmpAddCity` varchar(200) DEFAULT NULL,
  `EmpAddProv` varchar(200) DEFAULT NULL,
  `EmpAddZip` varchar(200) DEFAULT NULL,
  `EmpAddCountry` varchar(200) DEFAULT NULL,
  `EmpPhone` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpDOB` date DEFAULT NULL,
  `EmpGender` char(6) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpCS` char(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpMobile` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpEmail` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpPPNo` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpPPED` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpPPIA` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpSSS` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpTIN` varchar(200) DEFAULT NULL,
  `EmpHMONumber` varchar(200) DEFAULT NULL,
  `EmpPP` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpPPSD` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpPPDept` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpPPPos` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpPINo` varchar(200) DEFAULT NULL,
  `EmpPHNo` varchar(200) DEFAULT NULL,
  `EmpUMIDNo` varchar(200) DEFAULT NULL,
  `EmpPPath` varchar(200) DEFAULT NULL,
  `EmpCitezen` varchar(200) DEFAULT NULL,
  `EmpReligion` varchar(200) DEFAULT NULL,
  `EmpTaxCat` int(10) DEFAULT NULL,
  `card_number` varchar(250) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ProfSID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empstatus` (
  `EmpStatID` int(11) NOT NULL AUTO_INCREMENT,
  `EmpCODE` char(3) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `EmpStatDesc` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`EmpStatID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eovalidation` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `CompID` varchar(100) NOT NULL,
  `IsBefore` int(2) NOT NULL DEFAULT 1,
  `IsTardy` int(2) NOT NULL DEFAULT 1,
  `IsAfter` int(2) NOT NULL DEFAULT 1,
  `IsBeforeDays` int(2) NOT NULL DEFAULT 1,
  `IsAfterDays` int(2) NOT NULL DEFAULT 1,
  `IsLogoutNoIN` int(2) NOT NULL DEFAULT 1,
  `IsAutoLogout` int(11) NOT NULL,
  `workhours` int(11) NOT NULL DEFAULT 0,
  `sworkhours` int(11) DEFAULT 0,
  `DateTimeInputed` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estatus` (
  `ID` int(10) NOT NULL AUTO_INCREMENT,
  `StatusEmpDesc` varchar(50) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fdetails` (
  `FSID` int(10) NOT NULL AUTO_INCREMENT,
  `FDetID` varchar(50) NOT NULL,
  `FName` varchar(50) NOT NULL,
  `FAdd` varchar(50) NOT NULL,
  `FRel` varchar(20) NOT NULL,
  `FContact` varchar(12) NOT NULL,
  `FICE` varchar(10) NOT NULL,
  PRIMARY KEY (`FSID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frelationship` (
  `FRelID` int(10) NOT NULL AUTO_INCREMENT,
  `FRelDesc` varchar(50) NOT NULL,
  PRIMARY KEY (`FRelID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gmh` (
  `GMHID` int(11) NOT NULL,
  `GMHDate` date DEFAULT NULL,
  `GMHTimeFrom` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `GMHTimeTo` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hleaves` (
  `LeaveID` int(11) NOT NULL AUTO_INCREMENT,
  `FID` int(11) DEFAULT NULL,
  `EmpID` varchar(50) DEFAULT NULL,
  `EmpSID` varchar(50) DEFAULT NULL,
  `LType` int(11) DEFAULT NULL,
  `LFDate` date DEFAULT NULL,
  `LStart` date DEFAULT NULL,
  `LEnd` date DEFAULT NULL,
  `LPurpose` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `LDuration` double DEFAULT NULL,
  `LHD` int(11) DEFAULT 0,
  `LStatus` int(11) DEFAULT NULL,
  `LISReason` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `LHRReason` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `LInputDate` datetime(6) DEFAULT NULL,
  `LUpdate` datetime DEFAULT NULL,
  `LUpdateHR` datetime DEFAULT NULL,
  `LDateTimeUpdated` datetime(6) DEFAULT NULL,
  `LPaid` int(11) DEFAULT 1,
  PRIMARY KEY (`LeaveID`),
  KEY `ix_hleaves_emp` (`EmpID`),
  KEY `ix_hleaves_status` (`LStatus`,`LStart`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hleavesbd` (
  `LeaveID` int(11) NOT NULL AUTO_INCREMENT,
  `FID` int(11) DEFAULT NULL,
  `EmpID` varchar(50) NOT NULL,
  `EmpSID` varchar(50) NOT NULL,
  `LType` int(11) NOT NULL,
  `LFDate` date NOT NULL,
  `LStart` date NOT NULL,
  `LEnd` date NOT NULL,
  `LPurpose` varchar(500) NOT NULL,
  `LDuration` double NOT NULL,
  `LHD` int(11) NOT NULL DEFAULT 0,
  `LStatus` int(11) NOT NULL,
  `LISReason` varchar(500) DEFAULT NULL,
  `LHRReason` varchar(500) DEFAULT NULL,
  `LInputDate` datetime(6) DEFAULT NULL,
  `LUpdate` datetime DEFAULT NULL,
  `LUpdateHR` datetime DEFAULT NULL,
  `LDateTimeUpdated` datetime(6) DEFAULT NULL,
  `LPaid` int(11) NOT NULL,
  `am_pm` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`LeaveID`),
  KEY `ix_hleavesbd_emp_range` (`EmpID`,`LStart`,`LEnd`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hmo` (
  `HMO_SID` int(10) NOT NULL AUTO_INCREMENT,
  `HMO_ID` varchar(50) NOT NULL,
  `HMO_PROVIDER` varchar(50) NOT NULL,
  PRIMARY KEY (`HMO_SID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `SID` int(10) NOT NULL AUTO_INCREMENT,
  `Hdate` date NOT NULL,
  `Htype` varchar(50) NOT NULL,
  `Hdescription` varchar(50) NOT NULL,
  `HCompID` varchar(50) NOT NULL,
  `HOffsetEmpID` varchar(50) NOT NULL,
  PRIMARY KEY (`SID`),
  KEY `ix_holidays_date_comp` (`Hdate`,`HCompID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idcard_cards` (
  `EmpID` varchar(50) NOT NULL,
  `id_number` varchar(20) DEFAULT NULL,
  `issue_year` smallint(6) DEFAULT NULL,
  `seq` int(11) DEFAULT NULL,
  `photo_x` decimal(5,3) NOT NULL DEFAULT 0.000,
  `photo_y` decimal(5,3) NOT NULL DEFAULT 0.000,
  `photo_zoom` decimal(4,2) NOT NULL DEFAULT 1.00,
  `first_issued_at` datetime DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`EmpID`),
  UNIQUE KEY `ux_idcard_number` (`id_number`),
  UNIQUE KEY `ux_idcard_year_seq` (`issue_year`,`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idcard_print_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `action` varchar(10) NOT NULL,
  `prev_employee_id_number` varchar(100) DEFAULT NULL,
  `printed_by` varchar(50) NOT NULL,
  `printed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_idcard_log_emp` (`EmpID`,`printed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `idcard_settings` (
  `setting_key` varchar(40) NOT NULL,
  `setting_value` varchar(200) NOT NULL DEFAULT '',
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobdescription` (
  `JD_ID` int(11) NOT NULL AUTO_INCREMENT,
  `JDescription` varchar(100) NOT NULL,
  PRIMARY KEY (`JD_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `joblevel` (
  `SID` int(10) NOT NULL AUTO_INCREMENT,
  `jobLevelID` int(10) NOT NULL,
  `jobLevelDesc` varchar(50) NOT NULL,
  `CompanyID` varchar(50) NOT NULL,
  PRIMARY KEY (`SID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leaves` (
  `LeaveID` int(11) NOT NULL AUTO_INCREMENT,
  `LeaveDesc` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `Managers` int(11) DEFAULT NULL,
  `Supervisors` int(11) DEFAULT NULL,
  `RandFs` int(11) DEFAULT NULL,
  PRIMARY KEY (`LeaveID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leaves_validation` (
  `sid` int(10) NOT NULL AUTO_INCREMENT,
  `compid` varchar(100) NOT NULL,
  `lid` int(10) NOT NULL,
  `leave_credits` int(10) DEFAULT 0,
  `leave_short` int(10) DEFAULT 0,
  `leave_long` int(10) NOT NULL DEFAULT 0,
  `leave_min` int(11) NOT NULL DEFAULT 0,
  `leave_before` int(11) NOT NULL DEFAULT 0,
  `filing_before_duration` int(11) DEFAULT 0,
  `leave_file_after` int(11) NOT NULL DEFAULT 0,
  `filing_after_duration` int(11) NOT NULL DEFAULT 0,
  `max_days_before` int(11) DEFAULT 0,
  `file_during` int(11) DEFAULT 0,
  `IsHalfDay` int(11) NOT NULL DEFAULT 0,
  `val_is_bl_vac` int(11) DEFAULT NULL,
  `val_user_bl_vac` int(11) DEFAULT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lilovalidation` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `EmpCompID` varchar(100) NOT NULL,
  `EmpGP` int(15) NOT NULL,
  `DTInputed` datetime NOT NULL DEFAULT current_timestamp(),
  `ManagersOverride` int(11) DEFAULT 0,
  `ManagersTime` double DEFAULT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `listofpayee` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `payee` varchar(250) DEFAULT NULL,
  `accountno` varchar(250) DEFAULT NULL,
  `remarks` varchar(250) DEFAULT NULL,
  `can` varchar(500) NOT NULL,
  `dti` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_themes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `preset` varchar(40) NOT NULL,
  `season_label` varchar(60) DEFAULT NULL,
  `headline` varchar(80) DEFAULT NULL,
  `headline_accent` varchar(60) DEFAULT NULL,
  `message` varchar(300) DEFAULT NULL,
  `announcement` varchar(300) DEFAULT NULL,
  `show_effects` tinyint(1) NOT NULL DEFAULT 1,
  `banner_path` varchar(255) DEFAULT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `repeats_yearly` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_themes_active` (`is_active`,`starts_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `memo` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `memoto` varchar(250) NOT NULL,
  `memofrom` varchar(250) NOT NULL,
  `memoid` varchar(250) NOT NULL,
  `date` date NOT NULL,
  `subject` longtext NOT NULL,
  `body` longtext NOT NULL,
  `dti` datetime(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messageheader` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `MHID` varchar(100) NOT NULL,
  `SenderID` varchar(50) NOT NULL,
  `RecieverID` varchar(50) NOT NULL,
  `dateMessage` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `MSID` int(11) NOT NULL AUTO_INCREMENT,
  `MHID` varchar(100) NOT NULL,
  `SenderID` varchar(50) NOT NULL,
  `Message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `Kind` varchar(10) NOT NULL DEFAULT 'text',
  `DateSent` datetime NOT NULL,
  `DateRecieved` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`MSID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `starter` varchar(50) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'ringing',
  `end_reason` varchar(10) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `connected_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_calls_group` (`group_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_call_members` (
  `call_id` int(11) NOT NULL,
  `EmpID` varchar(50) NOT NULL,
  `state` varchar(10) NOT NULL,
  `joined_at` datetime DEFAULT NULL,
  `ping` datetime DEFAULT NULL,
  PRIMARY KEY (`call_id`,`EmpID`),
  KEY `ix_call_member` (`EmpID`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_call_signals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` int(11) NOT NULL,
  `sender` varchar(50) NOT NULL,
  `recipient` varchar(50) NOT NULL,
  `kind` varchar(10) NOT NULL,
  `payload` mediumtext NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_signals_to` (`call_id`,`recipient`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_group_members` (
  `group_id` int(11) NOT NULL,
  `EmpID` varchar(50) NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'member',
  `joined_at` datetime NOT NULL,
  `last_read` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`group_id`,`EmpID`),
  KEY `ix_group_member` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `msg_reactions` (
  `MSID` int(11) NOT NULL,
  `EmpID` varchar(50) NOT NULL,
  `Emoji` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `DateReacted` datetime NOT NULL,
  PRIMARY KEY (`MSID`,`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `msg_mentions` (
  `MSID` int(11) NOT NULL,
  `EmpID` varchar(50) NOT NULL,
  PRIMARY KEY (`MSID`,`EmpID`),
  KEY `ix_mention_emp` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_presence` (
  `EmpID` varchar(50) NOT NULL,
  `last_seen` datetime NOT NULL,
  `typing_to` varchar(50) DEFAULT NULL,
  `typing_at` datetime DEFAULT NULL,
  PRIMARY KEY (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `NotifType` varchar(50) NOT NULL,
  `NotifID` varchar(50) NOT NULL,
  `SenderID` varchar(50) NOT NULL,
  `RecieverID` varchar(50) NOT NULL,
  `Description` varchar(100) DEFAULT NULL,
  `Status` int(11) NOT NULL DEFAULT 1,
  `DateSent` datetime NOT NULL DEFAULT current_timestamp(),
  `DateRecieved` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obs` (
  `OBID` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) DEFAULT NULL,
  `EmpSID` varchar(50) DEFAULT NULL,
  `OBFD` date DEFAULT NULL,
  `OBDateFrom` date DEFAULT NULL,
  `OBDateTo` date DEFAULT NULL,
  `OBIFrom` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `OBDuration` double DEFAULT NULL,
  `OBITo` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `OBPurpose` varchar(2000) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `OBTimeFrom` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `OBTimeTo` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `OBCAAmt` decimal(10,0) DEFAULT NULL,
  `OBCAPurpose` varchar(2000) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `OBStatus` int(11) DEFAULT NULL,
  `OBUpdated` datetime NOT NULL,
  `OBISReason` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `OBHRReason` varchar(150) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `OBType` int(11) DEFAULT 1,
  `OBInputDate` datetime DEFAULT NULL,
  PRIMARY KEY (`OBID`),
  KEY `ix_obs_emp` (`EmpID`),
  KEY `ix_obs_status` (`OBStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obshbd` (
  `OBIDHBD` int(11) NOT NULL AUTO_INCREMENT,
  `OBID` int(11) DEFAULT NULL,
  `EmpID` varchar(50) DEFAULT NULL,
  `EmpSID` varchar(50) DEFAULT NULL,
  `OBFD` date DEFAULT NULL,
  `OBDateFrom` date DEFAULT NULL,
  `OBDateTo` date DEFAULT NULL,
  `OBIFrom` varchar(50) DEFAULT NULL,
  `OBDuration` double DEFAULT NULL,
  `OBITo` varchar(50) DEFAULT NULL,
  `OBPurpose` varchar(2000) DEFAULT NULL,
  `OBTimeFrom` varchar(50) DEFAULT NULL,
  `OBTimeTo` varchar(50) DEFAULT NULL,
  `OBCAAmt` decimal(10,0) DEFAULT NULL,
  `OBCAPurpose` varchar(2000) DEFAULT NULL,
  `OBStatus` int(11) DEFAULT NULL,
  `OBUpdated` datetime DEFAULT NULL,
  `OBISReason` varchar(150) DEFAULT NULL,
  `OBHRReason` varchar(150) DEFAULT NULL,
  `OBType` int(11) DEFAULT 1,
  `OBInputDate` datetime DEFAULT NULL,
  PRIMARY KEY (`OBIDHBD`),
  KEY `ix_obshbd_emp_range` (`EmpID`,`OBDateFrom`,`OBDateTo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obvalidation` (
  `SID` int(11) NOT NULL AUTO_INCREMENT,
  `compid` varchar(50) NOT NULL,
  `IsBefore` int(11) NOT NULL,
  `IsAfter` int(11) NOT NULL,
  `DaysBefore` int(11) DEFAULT NULL,
  `DaysAfter` int(11) DEFAULT NULL,
  PRIMARY KEY (`SID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otattendancelog` (
  `OTLOGID` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) NOT NULL,
  `EmpISID` varchar(50) NOT NULL,
  `TimeIn` datetime NOT NULL,
  `TimeOut` datetime DEFAULT NULL,
  `DateFiling` date NOT NULL,
  `TimeFiling` time NOT NULL,
  `Duration` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `OTPay` float NOT NULL,
  `ctype` varchar(20) NOT NULL,
  `Purpose` varchar(100) NOT NULL,
  `Status` int(11) NOT NULL,
  `ISReason` varchar(100) DEFAULT NULL,
  `HRReason` varchar(100) DEFAULT NULL,
  `ISUpdate` datetime DEFAULT current_timestamp(),
  `HRUpdate` datetime NOT NULL DEFAULT current_timestamp(),
  `DateTimeUpdate` datetime NOT NULL,
  `DateTimeInputed` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`OTLOGID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otfsmaintenance` (
  `orfsid` int(11) NOT NULL AUTO_INCREMENT,
  `compid` varchar(50) NOT NULL,
  `IsBefore` int(11) NOT NULL DEFAULT 0,
  `NoDaysBefore` int(11) NOT NULL DEFAULT 0,
  `IsAfter` int(11) NOT NULL DEFAULT 0,
  `NoDaysAfter` int(11) NOT NULL DEFAULT 0,
  `IsHoliday` int(11) NOT NULL DEFAULT 0,
  `IsTardy` int(11) NOT NULL DEFAULT 0,
  `IsDay` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`orfsid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagibig` (
  `PIID` int(11) NOT NULL AUTO_INCREMENT,
  `EE` float NOT NULL,
  `ER` float NOT NULL,
  PRIMARY KEY (`PIID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parentalrel` (
  `PIDE` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(50) NOT NULL,
  `DateofBirth` date NOT NULL,
  `Name` varchar(100) NOT NULL,
  PRIMARY KEY (`PIDE`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payrol` (
  `PYid` int(11) NOT NULL AUTO_INCREMENT,
  `PYEmpID` varchar(20) NOT NULL,
  `PYDate` date NOT NULL,
  `PYDateFrom` date NOT NULL,
  `PYDateTo` date NOT NULL,
  `PYBasic` float NOT NULL,
  `PYAllowance` float NOT NULL,
  `PYHourRate` float NOT NULL,
  `PYTAH` float NOT NULL,
  `PYTBH` float NOT NULL,
  `PYGross` double NOT NULL,
  `PYOverTime` double NOT NULL,
  `PYAdj` float NOT NULL,
  `PYAdjMin` float DEFAULT 0,
  `adjFromPCO` varchar(25) DEFAULT '0',
  `PYSSS` float NOT NULL,
  `PYSSSLoan` varchar(100) NOT NULL,
  `PYPhilHealth` float NOT NULL,
  `PYPagibig` float NOT NULL,
  `PYPILoan` varchar(100) NOT NULL,
  `PYTaxIncome` float NOT NULL,
  `PYIncTax` float NOT NULL,
  `PYOtherAdj` float NOT NULL DEFAULT 0,
  `PYOtherAdj2` float NOT NULL DEFAULT 0,
  `PYNetPay` double NOT NULL,
  `PYRecivable` double NOT NULL,
  `13thMon` varchar(101) DEFAULT NULL,
  `hrApproved` int(12) NOT NULL DEFAULT 0,
  `approvedate` date DEFAULT NULL,
  `PYssser` double NOT NULL DEFAULT 0,
  `PYphiler` double NOT NULL DEFAULT 0,
  `PYallowadj` double DEFAULT 0,
  `PYpier` double NOT NULL DEFAULT 0,
  `PYmfee` double NOT NULL DEFAULT 0,
  PRIMARY KEY (`PYid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `philhealth` (
  `PHSB` decimal(19,4) DEFAULT NULL,
  `SalaryFrom` decimal(19,4) DEFAULT NULL,
  `SalaryTo` decimal(19,4) DEFAULT NULL,
  `PHEE` decimal(19,4) DEFAULT NULL,
  `PHER` decimal(19,4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `positions` (
  `PSID` int(11) NOT NULL AUTO_INCREMENT,
  `PositionDesc` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `EmpJobLevelID` int(10) NOT NULL,
  PRIMARY KEY (`PSID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prdetails` (
  `PRDate` date DEFAULT NULL,
  `EmpID` int(11) DEFAULT NULL,
  `WDate` date DEFAULT NULL,
  `WDesc1` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `WDesc2` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `WDesc3` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `Wdesc4` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `WComp` decimal(19,4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profile_change_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(30) NOT NULL,
  `RequestedBy` varchar(30) NOT NULL,
  `Changes` longtext NOT NULL,
  `Status` varchar(10) NOT NULL DEFAULT 'pending',
  `ReviewedBy` varchar(30) DEFAULT NULL,
  `ReviewedAt` datetime DEFAULT NULL,
  `Remarks` varchar(500) DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pcr_status` (`Status`,`CreatedAt`),
  KEY `idx_pcr_emp` (`EmpID`,`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qt` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `question` varchar(3000) NOT NULL,
  `no` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restdays` (
  `RDID` int(11) NOT NULL,
  `RD1` char(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `RD2` char(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `RD3` char(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedeffectivity` (
  `efids` int(11) NOT NULL AUTO_INCREMENT,
  `dfrom` varchar(10) NOT NULL,
  `dto` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`efids`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedtime` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `empid` varchar(100) NOT NULL,
  `Monday` int(11) NOT NULL,
  `Tuesday` int(11) NOT NULL,
  `Wednesday` int(11) NOT NULL,
  `Thursday` int(11) NOT NULL,
  `Friday` int(11) NOT NULL,
  `Saturday` int(11) NOT NULL,
  `Sunday` int(11) NOT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `silloan` (
  `lid` int(11) NOT NULL AUTO_INCREMENT,
  `loanEmpID` varchar(250) NOT NULL,
  `loanAmount` varchar(50) NOT NULL,
  `LoanType` varchar(500) NOT NULL,
  `loanStatus` varchar(50) NOT NULL,
  `Date` date NOT NULL,
  PRIMARY KEY (`lid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sss` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `sssc` float NOT NULL,
  `SalaryFrom` float NOT NULL,
  `SalaryTo` double NOT NULL,
  `SSER` float NOT NULL,
  `SSEE` float NOT NULL,
  `SSEC` float NOT NULL,
  `WISPER` float NOT NULL,
  `WISPEE` float NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `status` (
  `StatusID` int(11) NOT NULL,
  `StatusDesc` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  PRIMARY KEY (`StatusID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taxtable` (
  `TID` int(10) NOT NULL AUTO_INCREMENT,
  `TaxCatDesc` varchar(100) NOT NULL,
  `RangeFrom` double NOT NULL,
  `RangeTo` double NOT NULL,
  `WTax` double NOT NULL,
  `Percentage` float NOT NULL,
  PRIMARY KEY (`TID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taxtablecat` (
  `TaxCatID` int(11) NOT NULL AUTO_INCREMENT,
  `TaxCatDesc` varchar(100) NOT NULL,
  PRIMARY KEY (`TaxCatID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `refID` varchar(12) DEFAULT NULL,
  `refEmpID` varchar(12) DEFAULT NULL,
  `refPayDate` date DEFAULT NULL,
  `duration` int(3) NOT NULL,
  `paid` int(1) DEFAULT 0,
  `inputdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_tsdrdash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tsdr_no` text NOT NULL,
  `items` text DEFAULT NULL,
  `requisitioner` text DEFAULT NULL,
  `approvedBy` text DEFAULT NULL,
  `req_status` int(11) NOT NULL DEFAULT 0,
  `appBy_status` int(11) NOT NULL DEFAULT 0,
  `req_approve_dateTime` text DEFAULT NULL,
  `appBy_approve_dateTime` text DEFAULT NULL,
  `dateTime_created` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblholidaypayroll` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `empid` varchar(13) NOT NULL,
  `pdate` date NOT NULL,
  `npdate` date DEFAULT NULL,
  `status` varchar(13) NOT NULL,
  `action` varchar(13) NOT NULL,
  `holiday_desc` varchar(150) DEFAULT NULL,
  `datetimeinputed` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblsyslog` (
  `id` int(15) NOT NULL AUTO_INCREMENT,
  `datecheck` date NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_payroll` (
  `sid` int(10) NOT NULL AUTO_INCREMENT,
  `EmpID` varchar(20) NOT NULL,
  `TypeOff` varchar(60) NOT NULL,
  `adjustment` varchar(10000) DEFAULT NULL,
  `adjmin` varchar(100) DEFAULT NULL,
  `Date` date DEFAULT NULL,
  `Pdate` date DEFAULT NULL,
  `DateImputed` datetime NOT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workdays` (
  `WID` int(10) NOT NULL AUTO_INCREMENT,
  `empid` varchar(100) DEFAULT NULL,
  `WDesc` varchar(50) DEFAULT NULL,
  `Day_s` varchar(11) DEFAULT NULL,
  `SchedTime` int(11) DEFAULT 0,
  `EffectivityDate` date DEFAULT NULL,
  `EFID` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`WID`),
  KEY `ix_workdays_emp_day` (`empid`,`Day_s`),
  KEY `ix_workdays_efid` (`EFID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workschedule` (
  `WorkSchedID` int(11) NOT NULL AUTO_INCREMENT,
  `TimeFrom` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `TimeTo` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `TimeCross` int(11) DEFAULT NULL,
  PRIMARY KEY (`WorkSchedID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- push notifications (sql/2026-10-07-add-push-devices.sql)
CREATE TABLE IF NOT EXISTS `push_devices` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`      VARCHAR(50)  NOT NULL,
  `token`      VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `platform`   VARCHAR(10)  NOT NULL DEFAULT 'android',   -- android | ios
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_devices_token` (`token`),
  KEY `ix_push_devices_emp` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- app sign-in tokens (sql/2026-10-07-add-app-remember.sql)
CREATE TABLE IF NOT EXISTS `app_remember` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `EmpID`      VARCHAR(50) NOT NULL,
  `token_hash` CHAR(64)    CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` DATETIME    NOT NULL,
  `created_at` DATETIME    NOT NULL,
  `last_used`  DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_remember_token` (`token_hash`),
  KEY `ix_app_remember_emp` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
