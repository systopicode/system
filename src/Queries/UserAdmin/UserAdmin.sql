-- User management in five result sets: users, roles, groups, who holds which
-- role, and what a role may do on a group. Row 1 of each table is the system's
-- own (recovery user, its role and group) and never shown.

SELECT usm_users.* FROM usm_users WHERE user_id != 1 ORDER BY user_id;
SELECT usm_roles.* FROM usm_roles WHERE role_id != 1 ORDER BY role_id;
SELECT usm_groups.* FROM usm_groups WHERE group_id != 1 ORDER BY group_id;
SELECT usm_users_at_roles.* FROM usm_users_at_roles WHERE uar_user_id != 1 AND uar_role_id != 1;
SELECT usm_roles_on_groups.* FROM usm_roles_on_groups WHERE rog_role_id != 1 AND rog_group_id != 1;
