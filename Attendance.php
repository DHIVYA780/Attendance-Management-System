from flask import Flask, render_template, request, redirect, url_for, flash, session, Response
from flask_sqlalchemy import SQLAlchemy
from werkzeug.security import generate_password_hash, check_password_hash
from datetime import datetime
import csv
from io import StringIO

app = Flask(__name__)
app.config[&#39;SECRET_KEY&#39;] = &#39;supersecretkey&#39;
app.config[&#39;SQLALCHEMY_DATABASE_URI&#39;] = &#39;sqlite:///attendance.db&#39;
app.config[&#39;SQLALCHEMY_TRACK_MODIFICATIONS&#39;] = False

db = SQLAlchemy(app)

# ------------------ MODELS ------------------

class User(db.Model):
id = db.Column(db.Integer, primary_key=True)
username = db.Column(db.String(100), unique=True)
password = db.Column(db.String(200))
role = db.Column(db.String(20))

class Student(db.Model):
id = db.Column(db.Integer, primary_key=True)
name = db.Column(db.String(100))
reg_no = db.Column(db.String(50), unique=True)
department = db.Column(db.String(100))
year = db.Column(db.Integer)
attendance = db.relationship(&#39;Attendance&#39;, backref=&#39;student&#39;, lazy=True)

class Subject(db.Model):
id = db.Column(db.Integer, primary_key=True)
name = db.Column(db.String(100))
attendance = db.relationship(&#39;Attendance&#39;, backref=&#39;subject&#39;, lazy=True)

class Attendance(db.Model):

id = db.Column(db.Integer, primary_key=True)
student_id = db.Column(db.Integer, db.ForeignKey(&#39;student.id&#39;))
subject_id = db.Column(db.Integer, db.ForeignKey(&#39;subject.id&#39;))
status = db.Column(db.String(10))
date = db.Column(db.Date, default=datetime.utcnow)

# ------------------ AUTH ------------------

@app.route(&#39;/&#39;, methods=[&#39;GET&#39;,&#39;POST&#39;])
def login():
if request.method == &#39;POST&#39;:
user = User.query.filter_by(username=request.form[&#39;username&#39;]).first()
if user and check_password_hash(user.password, request.form[&#39;password&#39;]):
session[&#39;role&#39;] = user.role
return redirect(url_for(&#39;dashboard&#39;))
flash(&quot;Invalid Credentials&quot;)
return render_template(&quot;login.html&quot;)

@app.route(&#39;/logout&#39;)
def logout():
session.clear()

return redirect(url_for(&#39;login&#39;))

# ------------------ DASHBOARD ------------------

@app.route(&#39;/dashboard&#39;)
def dashboard():
students = Student.query.all()
student_data = []

for s in students:
total = len(s.attendance)
present = len([a for a in s.attendance if a.status == &quot;Present&quot;])
percentage = (present/total)*100 if total &gt; 0 else 0
student_data.append({
&quot;student&quot;: s,
&quot;percentage&quot;: round(percentage,2),
&quot;low&quot;: percentage &lt; 75
})

return render_template(&quot;dashboard.html&quot;, student_data=student_data)

# ------------------ ADD DATA ------------------

@app.route(&#39;/add-student&#39;, methods=[&#39;GET&#39;,&#39;POST&#39;])
def add_student():
if request.method == &#39;POST&#39;:
s = Student(
name=request.form[&#39;name&#39;],
reg_no=request.form[&#39;reg_no&#39;],
department=request.form[&#39;department&#39;],
year=request.form[&#39;year&#39;]
)
db.session.add(s)
db.session.commit()
return redirect(url_for(&#39;dashboard&#39;))
return render_template(&quot;add_student.html&quot;)

@app.route(&#39;/add-subject&#39;, methods=[&#39;GET&#39;,&#39;POST&#39;])
def add_subject():
if request.method == &#39;POST&#39;:
db.session.add(Subject(name=request.form[&#39;name&#39;]))
db.session.commit()

return redirect(url_for(&#39;dashboard&#39;))
return render_template(&quot;add_subject.html&quot;)

@app.route(&#39;/mark-attendance&#39;, methods=[&#39;GET&#39;,&#39;POST&#39;])
def mark_attendance():
students = Student.query.all()
subjects = Subject.query.all()
if request.method == &#39;POST&#39;:
a = Attendance(
student_id=request.form[&#39;student&#39;],
subject_id=request.form[&#39;subject&#39;],
status=request.form[&#39;status&#39;]
)
db.session.add(a)
db.session.commit()
return redirect(url_for(&#39;dashboard&#39;))
return render_template(&quot;mark_attendance.html&quot;, students=students, subjects=subjects)

# ------------------ EXPORT CSV ------------------

@app.route(&#39;/export&#39;)

def export():
si = StringIO()
cw = csv.writer(si)
cw.writerow([&quot;Name&quot;,&quot;Reg No&quot;,&quot;Department&quot;,&quot;Year&quot;,&quot;Percentage&quot;])

students = Student.query.all()
for s in students:
total = len(s.attendance)
present = len([a for a in s.attendance if a.status == &quot;Present&quot;])
percentage = (present/total)*100 if total &gt; 0 else 0
cw.writerow([s.name, s.reg_no, s.department, s.year, round(percentage,2)])

output = Response(si.getvalue(), mimetype=&quot;text/csv&quot;)
output.headers[&quot;Content-Disposition&quot;] = &quot;attachment; filename=attendance_report.csv&quot;
return output

if __name__ == &quot;__main__&quot;:
with app.app_context():
db.create_all()
if not User.query.first():
db.session.add(User(username=&quot;admin&quot;,
password=generate_password_hash(&quot;admin123&quot;), role=&quot;admin&quot;))