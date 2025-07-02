import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';

const StudentDashboard = () => {
  const [studentData, setStudentData] = useState(null);

  useEffect(() => {
    // Fetch student data from API
    fetch('/api/student/profile', {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
      .then(response => response.json())
      .then(data => setStudentData(data))
      .catch(error => console.error('Error fetching data:', error));
  }, []);

  if (!studentData) return <div>Loading...</div>;

  return (
    <div className="container mx-auto p-4">
      <h1 className="text-2xl font-bold mb-4">Welcome, {studentData.name}</h1>
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-white p-4 shadow rounded">
          <h2 className="text-lg font-semibold">Grades</h2>
          <p>View your latest grades and academic performance.</p>
          <Link to="/grades" className="text-blue-500 hover:underline">View Grades</Link>
        </div>
        <div className="bg-white p-4 shadow rounded">
          <h2 className="text-lg font-semibold">Fee Payment</h2>
          <p>Pay your fees via M-Pesa.</p>
          <Link to="/payment" className="text-blue-500 hover:underline">Make Payment</Link>
        </div>
        <div className="bg-white p-4 shadow rounded">
          <h2 className="text-lg font-semibold">Resources</h2>
          <p>Access course materials and timetables.</p>
          <Link to="/resources" className="text-blue-500 hover:underline">View Resources</Link>
        </div>
      </div>
    </div>
  );
};

export default StudentDashboard;