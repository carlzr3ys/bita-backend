#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
BITA Portal - Email Sender (Python)
Uses Python smtplib to send emails via SMTP
Supports Gmail, Outlook, and custom SMTP servers

Usage:
    python send_email.py --to recipient@email.com --subject "Subject" --message "Body" --type approval --name "User Name"
    python send_email.py --to recipient@email.com --subject "Subject" --message "Body" --type rejection --name "User Name" --reason "Rejection reason"
"""

import sys
import argparse
import smtplib
import os
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from email.header import Header

# Email configuration
# You can set these as environment variables or change defaults here
SMTP_HOST = os.getenv('SMTP_HOST', 'smtp.gmail.com')
SMTP_PORT = int(os.getenv('SMTP_PORT', '587'))
SMTP_USER = os.getenv('SMTP_USER', '')  # Your email address
SMTP_PASS = os.getenv('SMTP_PASS', '')  # Your email password or app password
FROM_EMAIL = os.getenv('FROM_EMAIL', 'noreply@bita.utem.edu.my')
FROM_NAME = os.getenv('FROM_NAME', 'BITA Portal')

def get_approval_email_template(user_name, login_url):
    """Generate approval email HTML template"""
    return f"""
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body {{ font-family: Arial, sans-serif; line-height: 1.6; color: #333; }}
            .container {{ max-width: 600px; margin: 0 auto; padding: 20px; }}
            .header {{ background: linear-gradient(135deg, #3b82f6, #60a5fa); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }}
            .content {{ background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }}
            .button {{ display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }}
            .footer {{ text-align: center; margin-top: 20px; color: #6b7280; font-size: 0.875rem; }}
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Registration Approved!</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{user_name}</strong>,</p>
                <p>Good news! Your registration for the BITA Portal has been <strong style='color: #10b981;'>approved</strong> by our admin team.</p>
                <p>You can now login to your account and start using the portal.</p>
                <p style='text-align: center;'>
                    <a href='{login_url}' class='button'>Login to Portal</a>
                </p>
                <p>If you have any questions or need assistance, please contact the admin team.</p>
                <p>Best regards,<br><strong>BITA Portal Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    """

def get_rejection_email_template(user_name, reason=None):
    """Generate rejection email HTML template"""
    reason_text = f"<p><strong>Reason:</strong> {reason}</p>" if reason and reason.strip() else "<p>Unfortunately, your registration could not be approved at this time.</p>"
    
    return f"""
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body {{ font-family: Arial, sans-serif; line-height: 1.6; color: #333; }}
            .container {{ max-width: 600px; margin: 0 auto; padding: 20px; }}
            .header {{ background: linear-gradient(135deg, #ef4444, #f87171); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }}
            .content {{ background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }}
            .reason-box {{ background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 4px; }}
            .footer {{ text-align: center; margin-top: 20px; color: #6b7280; font-size: 0.875rem; }}
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Registration Not Approved</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{user_name}</strong>,</p>
                <p>We regret to inform you that your registration for the BITA Portal has not been approved.</p>
                <div class='reason-box'>
                    {reason_text}
                </div>
                <p>If you believe this is an error or would like to resubmit your registration, please contact the admin team for assistance.</p>
                <p>Best regards,<br><strong>BITA Portal Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    """

def send_email(to_email, subject, html_message, smtp_host=None, smtp_port=None, smtp_user=None, smtp_pass=None, from_email=None, from_name=None):
    """Send email using SMTP"""
    try:
        # Use defaults if not provided
        smtp_host = smtp_host or SMTP_HOST
        smtp_port = smtp_port or SMTP_PORT
        smtp_user = smtp_user or SMTP_USER
        smtp_pass = smtp_pass or SMTP_PASS
        from_email = from_email or FROM_EMAIL
        from_name = from_name or FROM_NAME
        
        # Create message
        msg = MIMEMultipart('alternative')
        msg['From'] = f"{from_name} <{from_email}>"
        msg['To'] = to_email
        msg['Subject'] = Header(subject, 'utf-8')
        msg['Reply-To'] = from_email
        
        # Add HTML content
        html_part = MIMEText(html_message, 'html', 'utf-8')
        msg.attach(html_part)
        
        # Connect to SMTP server and send
        if smtp_host and smtp_user and smtp_pass:
            # Use authenticated SMTP
            server = smtplib.SMTP(smtp_host, smtp_port)
            server.starttls()  # Enable TLS encryption
            server.login(smtp_user, smtp_pass)
            server.send_message(msg)
            server.quit()
            return True, "Email sent successfully via SMTP"
        else:
            # Try without authentication (may work on some servers)
            server = smtplib.SMTP(smtp_host, smtp_port)
            server.send_message(msg)
            server.quit()
            return True, "Email sent successfully (no auth)"
            
    except smtplib.SMTPAuthenticationError as e:
        return False, f"SMTP Authentication Error: {str(e)}"
    except smtplib.SMTPException as e:
        return False, f"SMTP Error: {str(e)}"
    except Exception as e:
        return False, f"Error: {str(e)}"

def main():
    parser = argparse.ArgumentParser(description='Send email via SMTP')
    parser.add_argument('--to', required=True, help='Recipient email address')
    parser.add_argument('--subject', help='Email subject (optional if using type)')
    parser.add_argument('--message', help='Email message/body (optional if using type)')
    parser.add_argument('--type', choices=['approval', 'rejection', 'custom'], help='Email type: approval, rejection, or custom')
    parser.add_argument('--name', help='User name (required for approval/rejection)')
    parser.add_argument('--reason', help='Rejection reason (optional for rejection type)')
    parser.add_argument('--login-url', help='Login URL (optional for approval type)')
    parser.add_argument('--smtp-host', help='SMTP host (default: smtp.gmail.com)')
    parser.add_argument('--smtp-port', type=int, help='SMTP port (default: 587)')
    parser.add_argument('--smtp-user', help='SMTP username/email')
    parser.add_argument('--smtp-pass', help='SMTP password')
    parser.add_argument('--from-email', help='From email address')
    parser.add_argument('--from-name', help='From name')
    
    args = parser.parse_args()
    
    # Determine subject and message
    if args.type == 'approval':
        if not args.name:
            print("ERROR: --name is required for approval type", file=sys.stderr)
            sys.exit(1)
        subject = "BITA Portal - Registration Approved"
        login_url = args.login_url or "http://localhost/bita/login"
        html_message = get_approval_email_template(args.name, login_url)
    elif args.type == 'rejection':
        if not args.name:
            print("ERROR: --name is required for rejection type", file=sys.stderr)
            sys.exit(1)
        subject = "BITA Portal - Registration Not Approved"
        html_message = get_rejection_email_template(args.name, args.reason)
    else:
        # Custom email
        if not args.subject or not args.message:
            print("ERROR: --subject and --message are required for custom type", file=sys.stderr)
            sys.exit(1)
        subject = args.subject
        html_message = args.message
    
    # Send email
    success, message = send_email(
        to_email=args.to,
        subject=subject,
        html_message=html_message,
        smtp_host=args.smtp_host,
        smtp_port=args.smtp_port,
        smtp_user=args.smtp_user,
        smtp_pass=args.smtp_pass,
        from_email=args.from_email,
        from_name=args.from_name
    )
    
    if success:
        print(f"SUCCESS: {message}")
        sys.exit(0)
    else:
        print(f"ERROR: {message}", file=sys.stderr)
        sys.exit(1)

if __name__ == '__main__':
    main()

