# Modelo de Dominio

## Objetivo

El sistema es una plataforma de gestión académica
multiinstitución.

Debe poder ser utilizado por:

- Universidades
- Institutos
- Colegios

El núcleo del sistema no debe depender de un tipo
específico de institución.

---

## Núcleo del dominio

### Institution

Representa una institución educativa que utiliza
la plataforma.

Una institución puede tener:

- Personas
- Programas académicos
- Períodos académicos

---

### Person

Representa una persona relacionada con la institución.

Una persona puede desempeñar diferentes roles.

Ejemplos:

- Estudiante
- Docente
- Personal administrativo

---

### Student

Representa a una persona que estudia dentro de
la institución.

Un estudiante puede tener múltiples matrículas
a lo largo del tiempo.

---

### Teacher

Representa a una persona que imparte actividades
académicas.

---

### AcademicProgram

Representa un programa educativo.

Ejemplos:

- Ingeniería de Software
- Desarrollo de Software
- Educación Secundaria

El sistema no debe asumir que un programa es
necesariamente una carrera.

---

### AcademicPlan

Representa una versión del plan académico de
un programa.

Ejemplos:

- Plan 2024
- Plan 2026

Un programa puede tener múltiples planes.

---

### AcademicLevel

Representa un nivel dentro de un plan académico.

Puede representar:

- Semestre
- Ciclo
- Módulo
- Grado escolar

El sistema no debe asumir un tipo específico.

---

### AcademicUnit

Representa una unidad académica.

Puede representar:

- Curso
- Asignatura
- Materia
- Módulo
- Taller

---

### AcademicPeriod

Representa el período durante el cual se desarrolla
la actividad académica.

Ejemplos:

- 2026-I
- 2026-II
- Año escolar 2026
- Módulo II 2026

---

### AcademicOffering

Representa una unidad académica que está siendo
ofrecida durante un período determinado.

Puede incluir:

- Docente
- Sección
- Horario
- Sede
- Capacidad

---

### Enrollment

Representa la matrícula de un estudiante en un
período académico.

Una matrícula puede incluir múltiples ofertas
académicas.

---

# Relaciones iniciales

Institution
→ Personas

Institution
→ Programas académicos

Institution
→ Períodos académicos

AcademicProgram
→ AcademicPlan

AcademicPlan
→ AcademicLevel

AcademicPlan
→ AcademicUnit

AcademicUnit
→ AcademicOffering

AcademicPeriod
→ AcademicOffering

Teacher
→ AcademicOffering

Student
→ Enrollment

Enrollment
→ AcademicOffering
## Relaciones del dominio

### Institución

Institution 1:N Person

Institution 1:N AcademicProgram

Institution 1:N AcademicPeriod


### Estructura académica

AcademicProgram 1:N AcademicPlan

AcademicPlan 1:N AcademicLevel

AcademicPlan 1:N CurriculumItem

AcademicLevel 1:N CurriculumItem

AcademicUnit 1:N CurriculumItem


### Operación académica

AcademicUnit 1:N AcademicOffering

AcademicPeriod 1:N AcademicOffering

Teacher 1:N AcademicOffering


### Matrícula

Student 1:N Enrollment

AcademicPeriod 1:N Enrollment

Enrollment N:M AcademicOffering

                         INSTITUTION
                              │
              ┌───────────────┼────────────────┐
              ↓               ↓                ↓
           PERSON         PROGRAM            PERIOD
              │          ACADEMIC             │
        ┌─────┴─────┐         │               │
        ↓           ↓         ↓               │
    STUDENT       TEACHER    PLAN              │
        │                    │                 │
        │                    ↓                 │
        │                  LEVEL               │
        │                    │                 │
        │                    ↓                 │
        │            CURRICULUM ITEM           │
        │                    │                 │
        │                    ↓                 │
        │              ACADEMIC UNIT           │
        │                    │                 │
        │                    └──────┬──────────┘
        │                           ↓
        │                    ACADEMIC OFFERING
        │                           │
        │                           │
        ↓                           │
     ENROLLMENT                     │
        │                           │
        └─────── ENROLLMENT DETAIL ─┘

        ## Relaciones académicas



Institution 1:N Campus

Institution 1:N Person

Institution 1:N AcademicProgram

Institution 1:N AcademicPeriod

AcademicProgram 1:N AcademicPlan

AcademicPlan 1:N AcademicLevel

AcademicPlan 1:N CurriculumItem

AcademicLevel 1:N CurriculumItem

AcademicUnit 1:N CurriculumItem

AcademicUnit 1:N AcademicOffering

AcademicPeriod 1:N AcademicOffering

Campus 1:N AcademicOffering

Student 1:N AcademicRecord

AcademicRecord N:1 AcademicProgram

AcademicRecord N:1 AcademicPlan

Teacher N:M AcademicOffering

Student 1:N Enrollment

AcademicPeriod 1:N Enrollment

Enrollment 1:N EnrollmentDetail

AcademicOffering 1:N EnrollmentDetail

//..

                ┌──────────────────┐
│   INSTITUTION    │
└────────┬─────────┘
         │
    ┌────┼───────────────┐
    │    │               │
    ▼    ▼               ▼
 Campus Person      AcademicProgram
          │                │
       ┌──┴──┐             ▼
       ▼     ▼       AcademicPlan
   Student Teacher         │
       │                   ▼
       │             AcademicLevel
       │                   │
       │                   ▼
       │          CurriculumItem
       │                   │
       │                   ▼
       │             AcademicUnit
       │                   │
       │                   ▼
       │           AcademicOffering ◄──── AcademicPeriod
       │                   │
       ▼                   │
 AcademicRecord            │
       │                   │
       ├── Program          │
       └── Plan             │
                           │
                           ▼
                      EnrollmentDetail
                           ▲
                           │
                      Enrollment
                           ▲
                           │
                        Student

---

### AcademicRecord

Representa la relación académica de un estudiante con un
programa y un plan.

Permite determinar qué está estudiando el estudiante.

---

### TeachingAssignment

Representa la asignación de un docente a una oferta académica.

Permite que una oferta tenga uno o varios docentes.

---

### Prerequisite

Representa el requisito académico necesario para cursar
una unidad académica.