import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Dashboard from './pages/Dashboard'
import Students from './pages/Students'
import CreateStudent from './pages/CreateStudent'
import EditStudent from './pages/EditStudent'
import StudentDetail from './pages/StudentDetail'
import Navbar from './components/Navbar'
import AcademicPrograms from './pages/AcademicPrograms'
import CreateAcademicProgram from './pages/CreateAcademicProgram'
import EditAcademicProgram from './pages/EditAcademicProgram'
import AcademicPlans from './pages/AcademicPlans'
import CreateAcademicPlan from './pages/CreateAcademicPlan'
import EditAcademicPlan from './pages/EditAcademicPlan'
import AcademicLevels from './pages/AcademicLevels'
import CreateAcademicLevel from './pages/CreateAcademicLevel'
import EditAcademicLevel from './pages/EditAcademicLevel'
import AcademicCurriculumItems from './pages/AcademicCurriculumItems'
import CreateAcademicCurriculumItem from './pages/CreateAcademicCurriculumItem'
import EditAcademicCurriculumItem from './pages/EditAcademicCurriculumItem'
import AcademicPeriods from './pages/AcademicPeriods'
import CreateAcademicPeriod from './pages/CreateAcademicPeriod'
import EditAcademicPeriod from './pages/EditAcademicPeriod'
import AcademicUnits from './pages/AcademicUnits'
import CreateAcademicUnit from './pages/CreateAcademicUnit'
import EditAcademicUnit from './pages/EditAcademicUnit'
import Campus from './pages/Campus'
import CreateCampus from './pages/CreateCampus'
import EditCampus from './pages/EditCampus'
import Persons from './pages/Persons'
import CreatePerson from './pages/CreatePerson'
import EditPerson from './pages/EditPerson'
import Teachers from './pages/Teachers'
import CreateTeacher from './pages/CreateTeacher'
import EditTeacher from './pages/EditTeacher'
import AcademicOfferings from './pages/AcademicOfferings'
import CreateAcademicOffering from './pages/CreateAcademicOffering'
import EditAcademicOffering from './pages/EditAcademicOffering'
import TeachingAssignments from './pages/TeachingAssignments'
import CreateTeachingAssignment from './pages/CreateTeachingAssignment'
import EditTeachingAssignment from './pages/EditTeachingAssignment'
import AcademicRecords from './pages/AcademicRecords'
import CreateAcademicRecord from './pages/CreateAcademicRecord'
import EditAcademicRecord from './pages/EditAcademicRecord'
import Enrollments from './pages/Enrollments'
import CreateEnrollment from './pages/CreateEnrollment'
import EditEnrollment from './pages/EditEnrollment'
import EnrollmentDetails from './pages/EnrollmentDetails'
import CreateEnrollmentDetail from './pages/CreateEnrollmentDetail'
import EditEnrollmentDetail from './pages/EditEnrollmentDetail'
import './App.css'

function App() {
  return (
    <BrowserRouter>
      <Navbar />

      <div className="page-container">
        <Routes>
          <Route path="/" element={<Dashboard />} />
          <Route path="/students" element={<Students />} />
          <Route path="/students/create" element={<CreateStudent />} />
          <Route path="/students/:id/edit" element={<EditStudent />} />
           <Route
            path="/students/:id"
            element={<StudentDetail />}
          />
          <Route path="/academic-programs" element={<AcademicPrograms />}/>
          <Route path="/academic-programs/create" element={<CreateAcademicProgram />}/>
          <Route path="/academic-programs/:id/edit" element={<EditAcademicProgram />}/>
          <Route path="/academic-plans" element={<AcademicPlans />}/>
          <Route path="/academic-plans/create" element={<CreateAcademicPlan />}/>
          <Route path="/academic-plans/:id/edit" element={<EditAcademicPlan />}/>
          <Route path="/academic-levels" element={<AcademicLevels />}/>
          <Route path="/academic-levels/create" element={<CreateAcademicLevel />}/>
          <Route path="/academic-levels/:id/edit" element={<EditAcademicLevel />}/>
          <Route path="/academic-curriculum-items" element={<AcademicCurriculumItems />}/>
          <Route path="/academic-curriculum-items/create" element={<CreateAcademicCurriculumItem />}/>
          <Route path="/academic-curriculum-items/:id/edit" element={<EditAcademicCurriculumItem />}/>
          <Route path="/academic-periods" element={<AcademicPeriods />}/>
          <Route path="/academic-periods/create" element={<CreateAcademicPeriod />}/>
          <Route path="/academic-periods/:id/edit" element={<EditAcademicPeriod />}/>
          <Route path="/academic-units" element={<AcademicUnits />}/>
          <Route path="/academic-units/create" element={<CreateAcademicUnit />}/>
          <Route path="/academic-units/:id/edit" element={<EditAcademicUnit />}/>
          <Route path="/campuses" element={<Campus />}/>
          <Route path="/campuses/create" element={<CreateCampus />}/>
          <Route path="/campuses/:id/edit" element={<EditCampus />}/>
          <Route path="/persons" element={<Persons />}/>
          <Route path="/persons/create" element={<CreatePerson />}/>
          <Route path="/persons/:id/edit" element={<EditPerson />}/>
          <Route path="/teachers" element={<Teachers />}/>
          <Route path="/teachers/create" element={<CreateTeacher />}/>
          <Route path="/teachers/:id/edit" element={<EditTeacher />}/>
          <Route path="/academic-offerings" element={<AcademicOfferings />}/>
          <Route path="/academic-offerings/create" element={<CreateAcademicOffering />}/>
          <Route path="/academic-offerings/:id/edit" element={<EditAcademicOffering />}/>
          <Route path="/teaching-assignments" element={<TeachingAssignments />}/>
          <Route path="/teaching-assignments/create" element={<CreateTeachingAssignment />}/>
          <Route path="/teaching-assignments/:id/edit" element={<EditTeachingAssignment />}/>
          <Route path="/academic-records" element={<AcademicRecords />}/>
          <Route path="/academic-records/create" element={<CreateAcademicRecord />}/>
          <Route path="/academic-records/:id/edit" element={<EditAcademicRecord />}/>
          <Route path="/enrollments" element={<Enrollments />}/>
          <Route path="/enrollments/create" element={<CreateEnrollment />}/>
          <Route path="/enrollments/:id/edit" element={<EditEnrollment />}/>
          <Route path="/enrollment-details" element={<EnrollmentDetails />} />
          <Route path="/enrollment-details/create" element={<CreateEnrollmentDetail />}/>
          <Route path="/enrollment-details/:id/edit" element={<EditEnrollmentDetail />}/>

        </Routes>
      </div>
    </BrowserRouter>
  )
}

export default App
