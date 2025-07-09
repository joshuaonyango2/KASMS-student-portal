<?php
require_once 'session_handler.php';
initializeSessionHandler();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hod') {
    header("Location: index.php");
    exit();
}

$conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get HOD's course dynamically from user_details based on user_id
$stmt = $conn->prepare("SELECT ud.designation FROM user_details ud JOIN users u ON ud.user_id = u.id WHERE u.id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$hod_designation = $result->fetch_assoc()['designation'];
$stmt->close();

// Extract course from designation and set profile title
$course = preg_replace('/^hod/', '', strtolower($hod_designation));
if ($course === 'nursing') {
    $course = 'Nursing';
    $profile_title = 'H.O.D Nursing';
} elseif ($course === 'clinicalmedicine') {
    $course = 'Clinical Medicine';
    $profile_title = 'H.O.D Clinical Medicine';
} else {
    $course = 'Unknown Course';
    $profile_title = 'H.O.D Profile';
}

if ($course === 'Nursing') $course = 'D.Nursing';
elseif ($course === 'Clinical Medicine') $course = 'D.Clinical Medicine';

// Get HOD profile details
$stmt = $conn->prepare("SELECT ud.name, ud.id_number, ud.gender, ud.phone, ud.email, ud.designation, ud.created_at 
                       FROM user_details ud 
                       JOIN users u ON ud.user_id = u.id 
                       WHERE u.id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get course statistics
$conn->begin_transaction();
try {
    // Registered student count
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT s.student_id) as student_count 
                           FROM students s 
                           JOIN registrations r ON s.student_id = r.student_id 
                           WHERE s.course = ? AND r.semester = ?");
    $semester = '2025-S1';
    $stmt->bind_param("ss", $course, $semester);
    $stmt->execute();
    $student_count = $stmt->get_result()->fetch_assoc()['student_count'];
    $stmt->close();
    
    // Instructor count
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT u.id) as instructor_count 
                           FROM users u 
                           JOIN user_details ud ON u.id = ud.user_id 
                           JOIN units un ON ud.designation LIKE CONCAT('%', ?, '%') 
                           WHERE u.role = 'instructor'");
    $stmt->bind_param("s", $course);
    $stmt->execute();
    $instructor_count = $stmt->get_result()->fetch_assoc()['instructor_count'];
    $stmt->close();
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Error: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $stmt = $conn->prepare("UPDATE user_details SET name = ?, phone = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $name, $phone, $email, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: hod_dashboard.php");
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0 && password_verify($current_password, $result->fetch_assoc()['password'])) {
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_password, $_SESSION['user_id']);
        $stmt->execute();
    }
    $stmt->close();
    header("Location: hod_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KASMS HOD Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            min-height: 100vh;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #1a2530 100%);
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .sidebar-link {
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-link::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: #3498db;
            transform: translateX(-10px);
            transition: transform 0.3s;
            opacity: 0;
        }
        
        .sidebar-link:hover::after {
            transform: translateX(0);
            opacity: 1;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            overflow: hidden;
            display: none;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            border-left: 4px solid;
        }
        
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .active-section {
            display: block;
        }
        
        .profile-container {
            background: linear-gradient(135deg, #6b48ff, #a166ff);
            padding: 2rem;
            border-radius: 15px;
            color: white;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .profile-container h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .profile-container .profile-image {
            width: 100px;
            height: 100px;
            background: #fff;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
        }
        
        .profile-container p {
            font-size: 1rem;
            margin: 0.5rem 0;
        }
        
        .profile-container strong {
            color: #e0d4ff;
        }
    </style>
</head>
<body class="flex min-h-screen">
    <!-- Sidebar -->
    <div class="sidebar w-64 text-white flex-shrink-0 hidden md:block">
        <div class="p-6">
            <div class="flex items-center space-x-3 mb-8">
                <div class="bg-gray-200 border-2 border-dashed rounded-xl w-16 h-16"></div>
                <div>
                    <h2 class="text-xl font-bold">KASMS Portal</h2>
                    <p class="text-sm text-blue-200">Head of Department</p>
                </div>
            </div>
            
            <nav>
                <a href="#" class="sidebar-link flex items-center py-3 px-6 text-blue-300 bg-blue-900/20">
                    <i class="fas fa-home mr-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="sidebar-link flex items-center py-3 px-6" onclick="showSection('students')">
                    <i class="fas fa-users mr-4"></i>
                    <span>Students</span>
                </a>
                <a href="#" class="sidebar-link flex items-center py-3 px-6" onclick="showSection('instructors')">
                    <i class="fas fa-chalkboard-teacher mr-4"></i>
                    <span>Instructors</span>
                </a>
                <a href="#" class="sidebar-link flex items-center py-3 px-6 mt-8" onclick="showSection('settings')">
                    <i class="fas fa-cog mr-4"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>
        
        <div class="absolute bottom-0 w-full p-4 border-t border-gray-700">
            <a href="logout.php" class="flex items-center py-2 px-4 text-red-400 hover:bg-gray-700 rounded">
                <i class="fas fa-sign-out-alt mr-3"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Mobile Header -->
        <header class="bg-white shadow md:hidden">
            <div class="flex justify-between items-center p-4">
                <button id="mobile-menu-button" class="text-gray-600">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-bold text-gray-800">KASMS Dashboard</h1>
                <div class="flex items-center">
                    <a href="logout.php" class="text-red-500">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Desktop Header -->
        <header class="bg-white shadow hidden md:block">
            <div class="flex justify-between items-center p-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">HOD Dashboard</h1>
                    <p class="text-sm text-gray-600">Welcome back, <?php echo htmlspecialchars($profile['name'] ?? 'HOD'); ?> - Logged in at 05:02 PM EAT, Tuesday, July 08, 2025</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <div class="bg-gray-200 border-2 border-dashed rounded-xl w-10 h-10 mr-3"></div>
                        <span class="text-gray-700 mr-2"><?php echo htmlspecialchars($profile['name'] ?? 'HOD'); ?></span>
                        <a href="logout.php" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <main class="flex-1 overflow-y-auto p-4 md:p-6">
            <!-- Dashboard Section with Enhanced Profile -->
            <div class="mb-6 text-center">
                <div class="profile-container">
                    <h2><?php echo $profile_title; ?></h2>
                    <div class="profile-image"></div>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($profile['name'] ?? 'N/A'); ?></p>
                    <p><strong>ID Number:</strong> <?php echo htmlspecialchars($profile['id_number'] ?? 'N/A'); ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($profile['gender'] ?? 'N/A'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone'] ?? 'N/A'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></p>
                    <p><strong>Designation:</strong> <?php echo htmlspecialchars($profile['designation'] ?? 'N/A'); ?></p>
                    <p><strong>Joined On:</strong> <?php echo date('F j, Y', strtotime($profile['created_at'] ?? 'N/A')); ?></p>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6 mb-8">
                <div class="card stat-card">
                    <div class="p-5">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Registered Students</p>
                                <h3 class="text-2xl font-bold mt-1"><?php echo $student_count; ?></h3>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-users text-blue-500 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-sm text-gray-500">Monitoring active students</span>
                        </div>
                    </div>
                </div>
                
                <div class="card stat-card">
                    <div class="p-5">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Instructors</p>
                                <h3 class="text-2xl font-bold mt-1"><?php echo $instructor_count; ?></h3>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-chalkboard-teacher text-purple-500 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-sm text-gray-500">Monitoring teaching staff</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monitoring Sections with Filters -->
            <div class="space-y-6">
                <!-- Registered Students List -->
                <div class="card" id="students">
                    <div class="p-5">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Registered Students</h3>
                        <div class="mb-4">
                            <select id="studentFilterYear" class="border p-2 rounded mr-2">
                                <option value="all">All Years</option>
                                <?php
                                $stmt = $conn->prepare("SELECT DISTINCT YEAR(r.semester) as year FROM registrations r JOIN students s ON r.student_id = s.student_id WHERE s.course = ?");
                                $stmt->bind_param("s", $course);
                                $stmt->execute();
                                $years = $stmt->get_result();
                                while ($year = $years->fetch_assoc()) {
                                    echo "<option value='{$year['year']}'>{$year['year']}</option>";
                                }
                                ?>
                            </select>
                            <select id="studentFilterSemester" class="border p-2 rounded mr-2">
                                <option value="all">All Semesters</option>
                                <option value="S1">Semester 1</option>
                                <option value="S2">Semester 2</option>
                            </select>
                            <button onclick="filterStudents()" class="bg-blue-500 text-white p-2 rounded">Filter</button>
                        </div>
                        <div id="studentList" class="space-y-4">
                            <?php
                            $stmt = $conn->prepare("SELECT s.student_id, s.name, r.unit_code, r.unit_name, r.semester 
                                                   FROM students s 
                                                   JOIN registrations r ON s.student_id = r.student_id 
                                                   WHERE s.course = ? AND r.semester = ? 
                                                   LIMIT 5");
                            $stmt->bind_param("ss", $course, $semester);
                            $stmt->execute();
                            $students = $stmt->get_result();
                            while ($student = $students->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium"><?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['student_id']); ?>)</p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($student['unit_name']); ?> (<?php echo htmlspecialchars($student['unit_code']); ?>) - <?php echo htmlspecialchars($student['semester']); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <?php if ($students->num_rows === 0): ?>
                                <p class="text-center text-gray-600">No students registered yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Instructors List -->
                <div class="card" id="instructors">
                    <div class="p-5">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Instructors</h3>
                        <div class="mb-4">
                            <select id="instructorFilterYear" class="border p-2 rounded mr-2">
                                <option value="all">All Years</option>
                                <?php
                                $stmt = $conn->prepare("SELECT DISTINCT YEAR(r.semester) as year FROM registrations r JOIN students s ON r.student_id = s.student_id JOIN user_details ud ON ud.designation LIKE CONCAT('%', ?, '%') JOIN users u ON ud.user_id = u.id WHERE u.role = 'instructor'");
                                $stmt->bind_param("s", $course);
                                $stmt->execute();
                                $years = $stmt->get_result();
                                while ($year = $years->fetch_assoc()) {
                                    echo "<option value='{$year['year']}'>{$year['year']}</option>";
                                }
                                ?>
                            </select>
                            <select id="instructorFilterSemester" class="border p-2 rounded mr-2">
                                <option value="all">All Semesters</option>
                                <option value="S1">Semester 1</option>
                                <option value="S2">Semester 2</option>
                            </select>
                            <button onclick="filterInstructors()" class="bg-blue-500 text-white p-2 rounded">Filter</button>
                        </div>
                        <div id="instructorList" class="space-y-4">
                            <?php
                            $stmt = $conn->prepare("SELECT u.id, ud.name, ud.designation, un.unit_code, un.unit_name, COUNT(r.student_id) as student_count 
                                                   FROM users u 
                                                   JOIN user_details ud ON u.id = ud.user_id 
                                                   JOIN units un ON ud.designation LIKE CONCAT('%', ?, '%') 
                                                   LEFT JOIN registrations r ON un.unit_code = r.unit_code 
                                                   WHERE u.role = 'instructor' 
                                                   GROUP BY u.id, ud.name, ud.designation, un.unit_code, un.unit_name 
                                                   LIMIT 5");
                            $stmt->bind_param("s", $course);
                            $stmt->execute();
                            $instructors = $stmt->get_result();
                            while ($instructor = $instructors->fetch_assoc()): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium"><?php echo htmlspecialchars($instructor['name']); ?> (ID: <?php echo htmlspecialchars($instructor['id']); ?>)</p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($instructor['unit_name']); ?> (<?php echo htmlspecialchars($instructor['unit_code']); ?>) - <?php echo $instructor['student_count']; ?> students</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <?php if ($instructors->num_rows === 0): ?>
                                <p class="text-center text-gray-600">No instructors assigned yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Section -->
                <div class="card" id="settings">
                    <div class="p-5">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Settings</h3>
                        <!-- Update Profile Form -->
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-700 mb-2">Update Profile</h4>
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm text-gray-600">Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" class="border p-2 rounded w-full" required>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600">Phone</label>
                                    <input type="text" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" class="border p-2 rounded w-full" required>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600">Email</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" class="border p-2 rounded w-full" required>
                                </div>
                                <button type="submit" name="update_profile" class="bg-green-500 text-white p-2 rounded">Update Profile</button>
                            </form>
                        </div>
                        <!-- Change Password Form -->
                        <div>
                            <h4 class="text-md font-semibold text-gray-700 mb-2">Change Password</h4>
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm text-gray-600">Current Password</label>
                                    <input type="password" name="current_password" class="border p-2 rounded w-full" required>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600">New Password</label>
                                    <input type="password" name="new_password" class="border p-2 rounded w-full" required>
                                </div>
                                <button type="submit" name="change_password" class="bg-green-500 text-white p-2 rounded">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('hidden');
        });

        // Show section and hide others
        function showSection(sectionId) {
            const sections = ['students', 'instructors', 'settings'];
            sections.forEach(id => {
                const section = document.getElementById(id);
                if (id === sectionId) {
                    section.classList.add('active-section');
                } else {
                    section.classList.remove('active-section');
                }
            });
        }

        // Filter Students
        function filterStudents() {
            const year = document.getElementById('studentFilterYear').value;
            const semester = document.getElementById('studentFilterSemester').value;
            let query = "SELECT s.student_id, s.name, r.unit_code, r.unit_name, r.semester FROM students s JOIN registrations r ON s.student_id = r.student_id WHERE s.course = ? ";
            const params = [<?php echo json_encode($course); ?>];
            if (year !== 'all') {
                query += "AND YEAR(r.semester) = ? ";
                params.push(year);
            }
            if (semester !== 'all') {
                query += "AND r.semester LIKE ? ";
                params.push('%' + semester);
            }
            query += "LIMIT 5";
            fetch('/filter_students.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: query, params: params })
            })
            .then(response => response.json())
            .then(data => {
                const studentList = document.getElementById('studentList');
                studentList.innerHTML = '';
                if (data.length === 0) {
                    studentList.innerHTML = '<p class="text-center text-gray-600">No students registered yet.</p>';
                } else {
                    data.forEach(student => {
                        studentList.innerHTML += `
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium">${student.name} (${student.student_id})</p>
                                    <p class="text-sm text-gray-600">${student.unit_name} (${student.unit_code}) - ${student.semester}</p>
                                </div>
                            </div>
                        `;
                    });
                }
            });
        }

        // Filter Instructors
        function filterInstructors() {
            const year = document.getElementById('instructorFilterYear').value;
            const semester = document.getElementById('instructorFilterSemester').value;
            let query = "SELECT u.id, ud.name, ud.designation, un.unit_code, un.unit_name, COUNT(r.student_id) as student_count FROM users u JOIN user_details ud ON u.id = ud.user_id JOIN units un ON ud.designation LIKE CONCAT('%', ?, '%') LEFT JOIN registrations r ON un.unit_code = r.unit_code WHERE u.role = 'instructor' ";
            const params = [<?php echo json_encode($course); ?>];
            if (year !== 'all') {
                query += "AND YEAR(r.semester) = ? ";
                params.push(year);
            }
            if (semester !== 'all') {
                query += "AND r.semester LIKE ? ";
                params.push('%' + semester);
            }
            query += "GROUP BY u.id, ud.name, ud.designation, un.unit_code, un.unit_name LIMIT 5";
            fetch('/filter_instructors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: query, params: params })
            })
            .then(response => response.json())
            .then(data => {
                const instructorList = document.getElementById('instructorList');
                instructorList.innerHTML = '';
                if (data.length === 0) {
                    instructorList.innerHTML = '<p class="text-center text-gray-600">No instructors assigned yet.</p>';
                } else {
                    data.forEach(instructor => {
                        instructorList.innerHTML += `
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium">${instructor.name} (ID: ${instructor.id})</p>
                                    <p class="text-sm text-gray-600">${instructor.unit_name} (${instructor.unit_code}) - ${instructor.student_count} students</p>
                                </div>
                            </div>
                        `;
                    });
                }
            });
        }
    </script>
</body>
</html>