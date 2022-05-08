db = db.getSiblingDB('admin')
db.createUser({
    user: 'admin',
    pwd: 'admin',
    roles: [
        {
            role: "root",
            db: "admin"
        }
    ]
});

db = db.getSiblingDB('test')
db.createUser({
    user: 'user',
    pwd: 'p#a!s@sw:o&r%^d',
    roles: [
        {
            role: "readWrite",
            db: "test"
        }
    ]
});

db = db.getSiblingDB('authDb')
db.createUser({
    user: 'userInAuthDb',
    pwd: 'p#a!s@sw:o&r%^d',
    roles: [
        {
            role: "readWrite",
            db: "test"
        }
    ]
});
