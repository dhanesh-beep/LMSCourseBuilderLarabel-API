import os

test={
    "quiz_full": [
        {
            "status": 200,
            "response": 200,
            "message": "Quiz created successfully",
            "data": {
                "quizzes": [
                    {
                        "id": 1,
                        "title": "Why we have this Policy",
                        "passing_score": 3,
                        "description": "<p>This quiz evaluates understanding of the purpose and objectives of the IET IT Acceptable Use Policy. It covers the policy's role in outlining acceptable use of IT equipment and services, and ensuring that all staff understand their responsibilities regarding IET equipment and services with harmonized, fair, and consistent standards.</p>",
                        "author": "AI Quiz Generator",
                        "questions": [
                            {
                                "id": 101,
                                "quiz_id": 1,
                                "question_title": "What is the primary purpose of the IET IT Acceptable Use Policy?",
                                "question_type": "single",
                                "question_order": "1",
                                "options": [
                                    {
                                        "id": 1001,
                                        "question_id": 101,
                                        "option_text": "To outline acceptable use of equipment and services provided by IT & Digital Services Directorate",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 1002,
                                        "question_id": 101,
                                        "option_text": "To provide financial guidelines for IT purchases",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 1003,
                                        "question_id": 101,
                                        "option_text": "To establish vacation policies for IT staff",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 1004,
                                        "question_id": 101,
                                        "option_text": "To define salary structures for employees",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 102,
                                "quiz_id": 1,
                                "question_title": "What does the policy seek to ensure regarding staff responsibilities?",
                                "question_type": "multiple",
                                "question_order": "2",
                                "options": [
                                    {
                                        "id": 1005,
                                        "question_id": 102,
                                        "option_text": "That all staff understand their responsibilities with regard to IET equipment and services",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 1006,
                                        "question_id": 102,
                                        "option_text": "That standards are harmonised, fair, and consistent",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 1007,
                                        "question_id": 102,
                                        "option_text": "That employees receive annual bonuses",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 1008,
                                        "question_id": 102,
                                        "option_text": "That staff can use personal devices without restriction",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 103,
                                "quiz_id": 1,
                                "question_title": "Which directorate provides the equipment and services covered by this policy?",
                                "question_type": "single",
                                "question_order": "3",
                                "options": [
                                    {
                                        "id": 1009,
                                        "question_id": 103,
                                        "option_text": "IT & Digital Services Directorate",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 1010,
                                        "question_id": 103,
                                        "option_text": "Human Resources Directorate",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 1011,
                                        "question_id": 103,
                                        "option_text": "Finance and Accounting Directorate",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 1012,
                                        "question_id": 103,
                                        "option_text": "Marketing and Communications Directorate",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 104,
                                "quiz_id": 1,
                                "question_title": "The policy helps staff carry out what aspect of their work?",
                                "question_type": "single",
                                "question_order": "4",
                                "options": [
                                    {
                                        "id": 1013,
                                        "question_id": 104,
                                        "option_text": "Their role using IET equipment and services",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 1014,
                                        "question_id": 104,
                                        "option_text": "Their personal financial planning",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 1015,
                                        "question_id": 104,
                                        "option_text": "Their social media activities",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 1016,
                                        "question_id": 104,
                                        "option_text": "Their recreational activities",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 105,
                                "quiz_id": 1,
                                "question_title": "What key objectives does the policy aim to achieve?",
                                "question_type": "multiple",
                                "question_order": "5",
                                "options": [
                                    {
                                        "id": 1017,
                                        "question_id": 105,
                                        "option_text": "Provide direction to ensure standards are harmonised",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 1018,
                                        "question_id": 105,
                                        "option_text": "Ensure standards are fair and consistent",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 1019,
                                        "question_id": 105,
                                        "option_text": "Increase employee salaries annually",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 1020,
                                        "question_id": 105,
                                        "option_text": "Eliminate all IT security measures",
                                        "is_correct": 0
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": 2,
                        "title": "Who this Policy relates to",
                        "passing_score": 3,
                        "description": "<p>This quiz assesses knowledge of the scope and applicability of the IET IT Acceptable Use Policy. It covers who must comply with the policy, including employees, volunteers, contractors, and temporary staff, as well as the consequences of non-compliance and mandatory training requirements.</p>",
                        "author": "AI Quiz Generator",
                        "questions": [
                            {
                                "id": 201,
                                "quiz_id": 2,
                                "question_title": "Who does the Acceptable Use Policy apply to?",
                                "question_type": "multiple",
                                "question_order": "1",
                                "options": [
                                    {
                                        "id": 2001,
                                        "question_id": 201,
                                        "option_text": "Employees",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2002,
                                        "question_id": 201,
                                        "option_text": "Volunteers",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2003,
                                        "question_id": 201,
                                        "option_text": "Contractors",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2004,
                                        "question_id": 201,
                                        "option_text": "Temporary staff with access to IET IT services",
                                        "is_correct": 1
                                    }
                                ]
                            },
                            {
                                "id": 202,
                                "quiz_id": 2,
                                "question_title": "What term is used in the policy document to refer to all persons within scope?",
                                "question_type": "single",
                                "question_order": "2",
                                "options": [
                                    {
                                        "id": 2005,
                                        "question_id": 202,
                                        "option_text": "Staff",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2006,
                                        "question_id": 202,
                                        "option_text": "Users",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2007,
                                        "question_id": 202,
                                        "option_text": "Members",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2008,
                                        "question_id": 202,
                                        "option_text": "Personnel",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 203,
                                "quiz_id": 2,
                                "question_title": "What are all staff contractually responsible for?",
                                "question_type": "multiple",
                                "question_order": "3",
                                "options": [
                                    {
                                        "id": 2009,
                                        "question_id": 203,
                                        "option_text": "Ensuring they have read the policy",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2010,
                                        "question_id": 203,
                                        "option_text": "Completing all mandatory data protection training",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2011,
                                        "question_id": 203,
                                        "option_text": "Completing all mandatory security training",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2012,
                                        "question_id": 203,
                                        "option_text": "Purchasing their own IT equipment",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 204,
                                "quiz_id": 2,
                                "question_title": "What may happen to staff who act contrary to this policy?",
                                "question_type": "single",
                                "question_order": "4",
                                "options": [
                                    {
                                        "id": 2013,
                                        "question_id": 204,
                                        "option_text": "They may be subject to action under the Disciplinary, Suspension and Appeals Policies",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2014,
                                        "question_id": 204,
                                        "option_text": "They will receive a warning only",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2015,
                                        "question_id": 204,
                                        "option_text": "Nothing will happen",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2016,
                                        "question_id": 204,
                                        "option_text": "They will be promoted",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 205,
                                "quiz_id": 2,
                                "question_title": "What disciplinary action may volunteers face if they act contrary to this policy?",
                                "question_type": "single",
                                "question_order": "5",
                                "options": [
                                    {
                                        "id": 2017,
                                        "question_id": 205,
                                        "option_text": "Action under the Disciplinary Regulations",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2018,
                                        "question_id": 205,
                                        "option_text": "Immediate termination without review",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2019,
                                        "question_id": 205,
                                        "option_text": "Financial penalties only",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2020,
                                        "question_id": 205,
                                        "option_text": "No consequences",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 206,
                                "quiz_id": 2,
                                "question_title": "When must all staff comply with the IET's Acceptable Use Policy?",
                                "question_type": "single",
                                "question_order": "6",
                                "options": [
                                    {
                                        "id": 2021,
                                        "question_id": 206,
                                        "option_text": "At all times",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2022,
                                        "question_id": 206,
                                        "option_text": "Only during working hours",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2023,
                                        "question_id": 206,
                                        "option_text": "Only when using office computers",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2024,
                                        "question_id": 206,
                                        "option_text": "Only during probation period",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 207,
                                "quiz_id": 2,
                                "question_title": "The policy applies to temporary staff under what condition?",
                                "question_type": "single",
                                "question_order": "7",
                                "options": [
                                    {
                                        "id": 2025,
                                        "question_id": 207,
                                        "option_text": "Where they have been supplied access to IET IT services or systems",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 2026,
                                        "question_id": 207,
                                        "option_text": "Only if they work more than 6 months",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2027,
                                        "question_id": 207,
                                        "option_text": "Only if they are full-time",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 2028,
                                        "question_id": 207,
                                        "option_text": "The policy does not apply to temporary staff",
                                        "is_correct": 0
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": 3,
                        "title": "Other Policies, Procedures and Guidelines",
                        "passing_score": 3,
                        "description": "<p>This quiz tests knowledge of the related policies, procedures, and guidelines that must be read in conjunction with the IT Acceptable Use Policy. It covers various supporting documents including disciplinary policies, security policies, data protection policies, and password guidance.</p>",
                        "author": "AI Quiz Generator",
                        "questions": [
                            {
                                "id": 301,
                                "quiz_id": 3,
                                "question_title": "Which policies relate to disciplinary actions mentioned in the Acceptable Use Policy?",
                                "question_type": "multiple",
                                "question_order": "1",
                                "options": [
                                    {
                                        "id": 3001,
                                        "question_id": 301,
                                        "option_text": "Disciplinary Policies",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3002,
                                        "question_id": 301,
                                        "option_text": "Appeals Policies",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3003,
                                        "question_id": 301,
                                        "option_text": "Suspension Policies",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3004,
                                        "question_id": 301,
                                        "option_text": "Vacation Policies",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 302,
                                "quiz_id": 3,
                                "question_title": "Which document provides guidance on password requirements?",
                                "question_type": "single",
                                "question_order": "2",
                                "options": [
                                    {
                                        "id": 3005,
                                        "question_id": 302,
                                        "option_text": "Password Guidance",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3006,
                                        "question_id": 302,
                                        "option_text": "Information Security Policy",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 3007,
                                        "question_id": 302,
                                        "option_text": "Data Protection Policy",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 3008,
                                        "question_id": 302,
                                        "option_text": "Disciplinary Regulations",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 303,
                                "quiz_id": 3,
                                "question_title": "Which policies are related to data protection and security?",
                                "question_type": "multiple",
                                "question_order": "3",
                                "options": [
                                    {
                                        "id": 3009,
                                        "question_id": 303,
                                        "option_text": "Information Security Policy",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3010,
                                        "question_id": 303,
                                        "option_text": "Data Protection Policy",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3011,
                                        "question_id": 303,
                                        "option_text": "Third Party Data Protection and Security Due Diligence Risk Procedure",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3012,
                                        "question_id": 303,
                                        "option_text": "Driving on Company Business policy",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 304,
                                "quiz_id": 3,
                                "question_title": "What is the Information Classification Policy used for?",
                                "question_type": "single",
                                "question_order": "4",
                                "options": [
                                    {
                                        "id": 3013,
                                        "question_id": 304,
                                        "option_text": "Classification and retention schedule of information",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3014,
                                        "question_id": 304,
                                        "option_text": "Employee performance reviews",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 3015,
                                        "question_id": 304,
                                        "option_text": "IT equipment purchasing",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 3016,
                                        "question_id": 304,
                                        "option_text": "Office space allocation",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 305,
                                "quiz_id": 3,
                                "question_title": "Which procedure relates to personal information risk management?",
                                "question_type": "single",
                                "question_order": "5",
                                "options": [
                                    {
                                        "id": 3017,
                                        "question_id": 305,
                                        "option_text": "Personal Information Risk Management Procedure",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 3018,
                                        "question_id": 305,
                                        "option_text": "Display Screen Equipment Assessments",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 3019,
                                        "question_id": 305,
                                        "option_text": "Driving on Company Business policy",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 3020,
                                        "question_id": 305,
                                        "option_text": "Disciplinary Regulations",
                                        "is_correct": 0
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": 4,
                        "title": "Overview of this Policy",
                        "passing_score": 3,
                        "description": "<p>This quiz evaluates understanding of the policy overview, including IET's ownership rights, monitoring capabilities, prohibited uses of IET resources, and restrictions on personal device usage. It covers lawful use requirements, prohibited activities, and the relationship with other acceptable use policies.</p>",
                        "author": "AI Quiz Generator",
                        "questions": [
                            {
                                "id": 401,
                                "quiz_id": 4,
                                "question_title": "Who is the legal owner of the systems and services provided to staff?",
                                "question_type": "single",
                                "question_order": "1",
                                "options": [
                                    {
                                        "id": 4001,
                                        "question_id": 401,
                                        "option_text": "The IET",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4002,
                                        "question_id": 401,
                                        "option_text": "The individual staff member",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4003,
                                        "question_id": 401,
                                        "option_text": "The IT & Digital Services Directorate",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4004,
                                        "question_id": 401,
                                        "option_text": "The government",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 402,
                                "quiz_id": 4,
                                "question_title": "What rights does the IET retain regarding provided systems?",
                                "question_type": "multiple",
                                "question_order": "2",
                                "options": [
                                    {
                                        "id": 4005,
                                        "question_id": 402,
                                        "option_text": "The right to monitor and view usage",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4006,
                                        "question_id": 402,
                                        "option_text": "The right to access unencrypted and encrypted content",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4007,
                                        "question_id": 402,
                                        "option_text": "The right to view any files created, stored, sent, or received",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4008,
                                        "question_id": 402,
                                        "option_text": "The right to share staff passwords with third parties",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 403,
                                "quiz_id": 4,
                                "question_title": "For what purposes does the IET monitor and access systems?",
                                "question_type": "multiple",
                                "question_order": "3",
                                "options": [
                                    {
                                        "id": 4009,
                                        "question_id": 403,
                                        "option_text": "Security purposes",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4010,
                                        "question_id": 403,
                                        "option_text": "Regulatory or legal compliance",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4011,
                                        "question_id": 403,
                                        "option_text": "Maintenance purposes",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4012,
                                        "question_id": 403,
                                        "option_text": "Disciplinary purposes",
                                        "is_correct": 1
                                    }
                                ]
                            },
                            {
                                "id": 404,
                                "quiz_id": 4,
                                "question_title": "Which of the following is a prohibited use of IET resources?",
                                "question_type": "single",
                                "question_order": "4",
                                "options": [
                                    {
                                        "id": 4013,
                                        "question_id": 404,
                                        "option_text": "Accessing services/systems for which you are not authorised",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4014,
                                        "question_id": 404,
                                        "option_text": "Sending work-related emails",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4015,
                                        "question_id": 404,
                                        "option_text": "Attending online training",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4016,
                                        "question_id": 404,
                                        "option_text": "Collaborating with colleagues",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 405,
                                "quiz_id": 4,
                                "question_title": "Which activities are explicitly prohibited when using IET resources?",
                                "question_type": "multiple",
                                "question_order": "5",
                                "options": [
                                    {
                                        "id": 4017,
                                        "question_id": 405,
                                        "option_text": "Intentionally introducing viruses, spyware, or malware",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4018,
                                        "question_id": 405,
                                        "option_text": "Causing intentional disruption to services",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4019,
                                        "question_id": 405,
                                        "option_text": "Breaching UK GDPR or Computer Misuse Act",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4020,
                                        "question_id": 405,
                                        "option_text": "Completing mandatory training",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 406,
                                "quiz_id": 4,
                                "question_title": "What types of content are prohibited from being published using IET resources?",
                                "question_type": "multiple",
                                "question_order": "6",
                                "options": [
                                    {
                                        "id": 4021,
                                        "question_id": 406,
                                        "option_text": "Defamatory content",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4022,
                                        "question_id": 406,
                                        "option_text": "Bullying content",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4023,
                                        "question_id": 406,
                                        "option_text": "Sexually explicit content",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4024,
                                        "question_id": 406,
                                        "option_text": "Content likely to incite racial hatred",
                                        "is_correct": 1
                                    }
                                ]
                            },
                            {
                                "id": 407,
                                "quiz_id": 4,
                                "question_title": "What is the policy regarding the use of personal devices to access IET systems?",
                                "question_type": "single",
                                "question_order": "7",
                                "options": [
                                    {
                                        "id": 4025,
                                        "question_id": 407,
                                        "option_text": "It is prohibited unless operating under approved exception",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4026,
                                        "question_id": 407,
                                        "option_text": "It is always allowed",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4027,
                                        "question_id": 407,
                                        "option_text": "It is only allowed for managers",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4028,
                                        "question_id": 407,
                                        "option_text": "It is encouraged for all staff",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 408,
                                "quiz_id": 4,
                                "question_title": "Which personal devices are mentioned as prohibited for accessing IET systems?",
                                "question_type": "multiple",
                                "question_order": "8",
                                "options": [
                                    {
                                        "id": 4029,
                                        "question_id": 408,
                                        "option_text": "Mobile phones",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4030,
                                        "question_id": 408,
                                        "option_text": "Tablets",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4031,
                                        "question_id": 408,
                                        "option_text": "Laptops",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4032,
                                        "question_id": 408,
                                        "option_text": "Smart speakers",
                                        "is_correct": 1
                                    }
                                ]
                            },
                            {
                                "id": 409,
                                "quiz_id": 4,
                                "question_title": "What should staff use to access IET systems?",
                                "question_type": "single",
                                "question_order": "9",
                                "options": [
                                    {
                                        "id": 4033,
                                        "question_id": 409,
                                        "option_text": "IET approved equipment only",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4034,
                                        "question_id": 409,
                                        "option_text": "Any personal device",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4035,
                                        "question_id": 409,
                                        "option_text": "Only home computers",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4036,
                                        "question_id": 409,
                                        "option_text": "Public library computers",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 410,
                                "quiz_id": 4,
                                "question_title": "Which laws or regulations are mentioned that must not be breached?",
                                "question_type": "multiple",
                                "question_order": "10",
                                "options": [
                                    {
                                        "id": 4037,
                                        "question_id": 410,
                                        "option_text": "UK GDPR",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4038,
                                        "question_id": 410,
                                        "option_text": "Computer Misuse Act",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4039,
                                        "question_id": 410,
                                        "option_text": "Copyright law",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4040,
                                        "question_id": 410,
                                        "option_text": "International technology embargoes",
                                        "is_correct": 1
                                    }
                                ]
                            },
                            {
                                "id": 411,
                                "quiz_id": 4,
                                "question_title": "What type of non-work related use is prohibited?",
                                "question_type": "single",
                                "question_order": "11",
                                "options": [
                                    {
                                        "id": 4041,
                                        "question_id": 411,
                                        "option_text": "Excessive use for non-work related activities",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4042,
                                        "question_id": 411,
                                        "option_text": "Any use for non-work related activities",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4043,
                                        "question_id": 411,
                                        "option_text": "Minimal use for non-work related activities",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4044,
                                        "question_id": 411,
                                        "option_text": "Occasional use for non-work related activities",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 412,
                                "quiz_id": 4,
                                "question_title": "What type of commercial activities are prohibited?",
                                "question_type": "single",
                                "question_order": "12",
                                "options": [
                                    {
                                        "id": 4045,
                                        "question_id": 412,
                                        "option_text": "Commercial activities that do not relate to IET business",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4046,
                                        "question_id": 412,
                                        "option_text": "All commercial activities",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4047,
                                        "question_id": 412,
                                        "option_text": "Commercial activities approved by managers",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4048,
                                        "question_id": 412,
                                        "option_text": "No commercial activities are prohibited",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 413,
                                "quiz_id": 4,
                                "question_title": "What should staff be aware of regarding other systems they may use?",
                                "question_type": "single",
                                "question_order": "13",
                                "options": [
                                    {
                                        "id": 4049,
                                        "question_id": 413,
                                        "option_text": "They may have their own Acceptable Use Policies that must be adhered to",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4050,
                                        "question_id": 413,
                                        "option_text": "They do not require any policy compliance",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4051,
                                        "question_id": 413,
                                        "option_text": "They override the IET policy",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4052,
                                        "question_id": 413,
                                        "option_text": "They are not relevant to IET staff",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 414,
                                "quiz_id": 4,
                                "question_title": "How should other Acceptable Use Policies be treated in relation to the IET policy?",
                                "question_type": "single",
                                "question_order": "14",
                                "options": [
                                    {
                                        "id": 4053,
                                        "question_id": 414,
                                        "option_text": "As supplementary to the IET policy",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4054,
                                        "question_id": 414,
                                        "option_text": "As replacements for the IET policy",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4055,
                                        "question_id": 414,
                                        "option_text": "As optional guidelines",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4056,
                                        "question_id": 414,
                                        "option_text": "As irrelevant to IET staff",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 415,
                                "quiz_id": 4,
                                "question_title": "What is required of all staff regarding the use of IET resources?",
                                "question_type": "single",
                                "question_order": "15",
                                "options": [
                                    {
                                        "id": 4057,
                                        "question_id": 415,
                                        "option_text": "Exercising good judgment regarding appropriate use in accordance with IET policies",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 4058,
                                        "question_id": 415,
                                        "option_text": "Using resources without any restrictions",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4059,
                                        "question_id": 415,
                                        "option_text": "Sharing access with family members",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 4060,
                                        "question_id": 415,
                                        "option_text": "Ignoring policy guidelines",
                                        "is_correct": 0
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        "id": 5,
                        "title": "Service Access and Password/Account Management",
                        "passing_score": 3,
                        "description": "<p>This quiz assesses understanding of service access procedures, password management requirements, and account security responsibilities. It covers access authorization processes, password confidentiality, computer security practices, and procedures for handling compromised credentials.</p>",
                        "author": "AI Quiz Generator",
                        "questions": [
                            {
                                "id": 501,
                                "quiz_id": 5,
                                "question_title": "How are staff provided with access to IET systems?",
                                "question_type": "single",
                                "question_order": "1",
                                "options": [
                                    {
                                        "id": 5001,
                                        "question_id": 501,
                                        "option_text": "Based on systems that are relevant to their role",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5002,
                                        "question_id": 501,
                                        "option_text": "All staff receive access to all systems",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5003,
                                        "question_id": 501,
                                        "option_text": "Only senior managers receive system access",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5004,
                                        "question_id": 501,
                                        "option_text": "Access is randomly assigned",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 502,
                                "quiz_id": 5,
                                "question_title": "What should you do if you require access to additional systems?",
                                "question_type": "single",
                                "question_order": "2",
                                "options": [
                                    {
                                        "id": 5005,
                                        "question_id": 502,
                                        "option_text": "Raise an IT Service Centre ticket outlining the reason for access",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5006,
                                        "question_id": 502,
                                        "option_text": "Access the system without permission",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5007,
                                        "question_id": 502,
                                        "option_text": "Use a colleague's login credentials",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5008,
                                        "question_id": 502,
                                        "option_text": "Wait until annual review",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 503,
                                "quiz_id": 5,
                                "question_title": "Who must authorize access to additional systems?",
                                "question_type": "multiple",
                                "question_order": "3",
                                "options": [
                                    {
                                        "id": 5009,
                                        "question_id": 503,
                                        "option_text": "Your line manager",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5010,
                                        "question_id": 503,
                                        "option_text": "The owner of the system",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5011,
                                        "question_id": 503,
                                        "option_text": "Any colleague",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5012,
                                        "question_id": 503,
                                        "option_text": "The IT Service Centre alone",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 504,
                                "quiz_id": 5,
                                "question_title": "What level of access will staff be provided with?",
                                "question_type": "single",
                                "question_order": "4",
                                "options": [
                                    {
                                        "id": 5013,
                                        "question_id": 504,
                                        "option_text": "Only the level necessary and relevant to their role",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5014,
                                        "question_id": 504,
                                        "option_text": "Full administrative access to all systems",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5015,
                                        "question_id": 504,
                                        "option_text": "The highest level available",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5016,
                                        "question_id": 504,
                                        "option_text": "Read-only access to everything",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 505,
                                "quiz_id": 5,
                                "question_title": "Who is accountable for actions completed using login credentials?",
                                "question_type": "single",
                                "question_order": "5",
                                "options": [
                                    {
                                        "id": 5017,
                                        "question_id": 505,
                                        "option_text": "The staff member whose credentials are used",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5018,
                                        "question_id": 505,
                                        "option_text": "The IT department",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5019,
                                        "question_id": 505,
                                        "option_text": "The line manager",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5020,
                                        "question_id": 505,
                                        "option_text": "No one is accountable",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 506,
                                "quiz_id": 5,
                                "question_title": "When must your computer be locked?",
                                "question_type": "single",
                                "question_order": "6",
                                "options": [
                                    {
                                        "id": 5021,
                                        "question_id": 506,
                                        "option_text": "Whenever you are away from your desk",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5022,
                                        "question_id": 506,
                                        "option_text": "Only at the end of the day",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5023,
                                        "question_id": 506,
                                        "option_text": "Only when leaving the building",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5024,
                                        "question_id": 506,
                                        "option_text": "Locking is optional",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 507,
                                "quiz_id": 5,
                                "question_title": "Why should computers be locked when away from the desk?",
                                "question_type": "single",
                                "question_order": "7",
                                "options": [
                                    {
                                        "id": 5025,
                                        "question_id": 507,
                                        "option_text": "To help prevent unauthorised access to IET systems and data",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5026,
                                        "question_id": 507,
                                        "option_text": "To save electricity",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5027,
                                        "question_id": 507,
                                        "option_text": "To improve computer performance",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5028,
                                        "question_id": 507,
                                        "option_text": "To comply with health and safety regulations",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 508,
                                "quiz_id": 5,
                                "question_title": "What are staff responsible for regarding passwords?",
                                "question_type": "multiple",
                                "question_order": "8",
                                "options": [
                                    {
                                        "id": 5029,
                                        "question_id": 508,
                                        "option_text": "Creating appropriate passwords",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5030,
                                        "question_id": 508,
                                        "option_text": "Keeping passwords confidential",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5031,
                                        "question_id": 508,
                                        "option_text": "Using appropriate passwords",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5032,
                                        "question_id": 508,
                                        "option_text": "Sharing passwords with managers",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 509,
                                "quiz_id": 5,
                                "question_title": "Where can staff find the criteria for creating passwords?",
                                "question_type": "single",
                                "question_order": "9",
                                "options": [
                                    {
                                        "id": 5033,
                                        "question_id": 509,
                                        "option_text": "In the Password Guidance document",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5034,
                                        "question_id": 509,
                                        "option_text": "In the Data Protection Policy",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5035,
                                        "question_id": 509,
                                        "option_text": "In the Disciplinary Regulations",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5036,
                                        "question_id": 509,
                                        "option_text": "In the Information Security Policy",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 510,
                                "quiz_id": 5,
                                "question_title": "Is it acceptable to share your password with anyone?",
                                "question_type": "single",
                                "question_order": "10",
                                "options": [
                                    {
                                        "id": 5037,
                                        "question_id": 510,
                                        "option_text": "No, never share passwords with anyone",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5038,
                                        "question_id": 510,
                                        "option_text": "Yes, with your manager only",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5039,
                                        "question_id": 510,
                                        "option_text": "Yes, with system administrators",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5040,
                                        "question_id": 510,
                                        "option_text": "Yes, with personal assistants",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 511,
                                "quiz_id": 5,
                                "question_title": "Who should never be given your password?",
                                "question_type": "multiple",
                                "question_order": "11",
                                "options": [
                                    {
                                        "id": 5041,
                                        "question_id": 511,
                                        "option_text": "Managers",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5042,
                                        "question_id": 511,
                                        "option_text": "Directors",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5043,
                                        "question_id": 511,
                                        "option_text": "System administrators",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5044,
                                        "question_id": 511,
                                        "option_text": "Personal assistants",
                                        "is_correct": 1
                                    }
                                ]
                            },
                            {
                                "id": 512,
                                "quiz_id": 5,
                                "question_title": "How should all passwords be treated?",
                                "question_type": "single",
                                "question_order": "12",
                                "options": [
                                    {
                                        "id": 5045,
                                        "question_id": 512,
                                        "option_text": "As confidential IET information",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5046,
                                        "question_id": 512,
                                        "option_text": "As public information",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5047,
                                        "question_id": 512,
                                        "option_text": "As shareable with trusted colleagues",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5048,
                                        "question_id": 512,
                                        "option_text": "As optional security measures",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 513,
                                "quiz_id": 5,
                                "question_title": "Is it acceptable to provide your password to a colleague covering your work during holidays?",
                                "question_type": "single",
                                "question_order": "13",
                                "options": [
                                    {
                                        "id": 5049,
                                        "question_id": 513,
                                        "option_text": "No, it is unacceptable",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5050,
                                        "question_id": 513,
                                        "option_text": "Yes, it is acceptable",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5051,
                                        "question_id": 513,
                                        "option_text": "Yes, if approved by your manager",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5052,
                                        "question_id": 513,
                                        "option_text": "Yes, if it's temporary",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 514,
                                "quiz_id": 5,
                                "question_title": "What should you do if you suspect your password has been compromised?",
                                "question_type": "multiple",
                                "question_order": "14",
                                "options": [
                                    {
                                        "id": 5053,
                                        "question_id": 514,
                                        "option_text": "Change your password immediately",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5054,
                                        "question_id": 514,
                                        "option_text": "Report this to the IT Service Centre without delay",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5055,
                                        "question_id": 514,
                                        "option_text": "Wait to see if anything happens",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5056,
                                        "question_id": 514,
                                        "option_text": "Ignore it if you're not sure",
                                        "is_correct": 0
                                    }
                                ]
                            },
                            {
                                "id": 515,
                                "quiz_id": 5,
                                "question_title": "Should anyone ever ask you for your password?",
                                "question_type": "single",
                                "question_order": "15",
                                "options": [
                                    {
                                        "id": 5057,
                                        "question_id": 515,
                                        "option_text": "No, no one should ever ask for your password",
                                        "is_correct": 1
                                    },
                                    {
                                        "id": 5058,
                                        "question_id": 515,
                                        "option_text": "Yes, managers can ask for it",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5059,
                                        "question_id": 515,
                                        "option_text": "Yes, IT support can ask for it",
                                        "is_correct": 0
                                    },
                                    {
                                        "id": 5060,
                                        "question_id": 515,
                                        "option_text": "Yes, during security audits",
                                        "is_correct": 0
                                    }
                                ]
                            }
                        ]
                    }
                ]
            },
            "quiz_evaluation_matrix": {
                "grounding_score": 95,
                "accuracy_score": 93,
                "context_token_overlap": 88,
                "response_length_score": 92,
                "relevance_score": 96,
                "evaluation_summary": "Quiz generation is highly grounded in source content, factually accurate, and demonstrates strong relevance to all policy sections with appropriate question distribution across all five lessons."
            }
        }]}

print(test['quiz_full'][0]['quiz_evaluation_matrix'])
